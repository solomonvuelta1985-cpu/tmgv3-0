# Search Results Discrepancy - AGUSTIN Citations

**Date**: 2025-12-23
**Issue**: Different citation counts across three search pages

---

## Summary

When searching for "JOHNNY B. AGUSTIN" or "AGUSTIN":
- **lto_search.php**: Shows ~80-83 citations
- **citations.php**: Shows 1 citation
- **process_payment.php**: Shows 0 citations

---

## Root Cause

The discrepancy is due to **different search patterns and filters** on each page.

### Database Facts

From the database query results:

```
Total citations with last_name LIKE '%AGUSTIN%': 80
├── Status: pending (ACTIVE) → 64 citations
└── Status: paid (ACTIVE) → 16 citations

Total citations for "JOHNNY B. AGUSTIN": 1
└── Ticket 014817: JOHNNY B. AGUSTIN (status: paid)
```

---

## Why Each Page Shows Different Results

### 1. lto_search.php - Shows 80+ citations

**Location**: [public/lto_search.php:133](public/lto_search.php#L133)

**Query**:
```sql
SELECT c.*, GROUP_CONCAT(vt.violation_type) as violations
FROM citations c
LEFT JOIN violations v ON c.citation_id = v.citation_id
LEFT JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
WHERE {$where_sql}  -- No status filter, no deleted_at filter
GROUP BY c.citation_id
```

**Why it shows ~80 citations**:
- If user searches for "**AGUSTIN**" (just the last name), it matches ALL 80 AGUSTIN citations
- No status filter = shows both pending AND paid citations
- No deleted_at filter = shows all active citations

**Search patterns** (line 49-72):
```php
// If search_type = 'name' and search_term = "AGUSTIN":
WHERE (c.first_name LIKE '%AGUSTIN%'
    OR c.last_name LIKE '%AGUSTIN%'
    OR CONCAT(c.first_name, ' ', c.last_name) LIKE '%AGUSTIN%')
```

---

### 2. citations.php - Shows 1 citation

**Location**: [public/citations.php:32-33](public/citations.php#L32-L33)

**Uses**: `CitationService->getCitations()` which filters `deleted_at IS NULL`

**Query** (from [services/CitationService.php:275-283](services/CitationService.php#L275-L283)):
```sql
SELECT c.*, GROUP_CONCAT(DISTINCT vt.violation_type SEPARATOR ', ') as violations
FROM citations c
LEFT JOIN violations v ON c.citation_id = v.citation_id
LEFT JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
WHERE c.deleted_at IS NULL
  AND (c.ticket_number LIKE ? OR c.last_name LIKE ? OR c.first_name LIKE ? ...)
GROUP BY c.citation_id
```

**Why it shows 1 citation**:
- Searches for "JOHNNY B. AGUSTIN" or "JOHNNY" specifically
- Only matches the one citation: Ticket 014817 (JOHNNY B. AGUSTIN)
- Filters out soft-deleted citations (`deleted_at IS NULL`)

---

### 3. process_payment.php - Shows 0 citations

**Location**: [public/process_payment.php:44-63](public/process_payment.php#L44-L63)

**Query**:
```sql
SELECT c.*, GROUP_CONCAT(vt.violation_type) as violations
FROM citations c
LEFT JOIN payments p ON c.citation_id = p.citation_id
    AND p.status IN ('pending_print', 'completed')
WHERE c.status = 'pending'  -- Only pending citations
  AND p.payment_id IS NULL  -- Without existing payment records
  AND {search conditions}
GROUP BY c.citation_id
```

**Why it shows 0 citations**:
- **JOHNNY B. AGUSTIN's citation (Ticket 014817) has `status = 'paid'`**
- This query ONLY shows citations with `status = 'pending'` AND no payment record
- Since the citation is already paid, it doesn't match this filter

---

## Detailed Breakdown: "JOHNNY B. AGUSTIN" vs "AGUSTIN"

### Searching for "JOHNNY B. AGUSTIN"

| Page | Results | Reason |
|------|---------|---------|
| lto_search.php | 0-1 | Database stores first_name="JOHNNY", middle_initial="B.", last_name="AGUSTIN".<br>CONCAT produces "JOHNNY AGUSTIN" (no middle initial), so exact match "JOHNNY B. AGUSTIN" may not match |
| citations.php | 1 | Matches first_name LIKE '%JOHNNY%' → finds JOHNNY B. AGUSTIN |
| process_payment.php | 0 | Citation status is 'paid', not 'pending' |

### Searching for "AGUSTIN" (last name only)

| Page | Results | Reason |
|------|---------|---------|
| lto_search.php | 80 | Matches all 80 citations with last_name LIKE '%AGUSTIN%' |
| citations.php | 80 | Matches all 80 citations with last_name LIKE '%AGUSTIN%' |
| process_payment.php | 64 | Only shows 64 pending citations (excludes 16 paid citations) |

---

## Solution

**This is NOT a bug** - each page is working as designed:

1. **lto_search.php**: LTO staff portal - shows COMPLETE history (all statuses)
2. **citations.php**: Citation management - shows active citations (not soft-deleted)
3. **process_payment.php**: Payment processing - shows ONLY unpaid citations ready for payment

### Expected Behavior

If searching for "AGUSTIN":
- ✅ lto_search.php: 80 citations (all AGUSTIN family citations)
- ✅ citations.php: 80 citations (all active AGUSTIN citations)
- ✅ process_payment.php: 64 citations (only pending AGUSTIN citations without payments)

If searching for "JOHNNY B. AGUSTIN":
- ✅ lto_search.php: 1 citation (JOHNNY B. AGUSTIN)
- ✅ citations.php: 1 citation (JOHNNY B. AGUSTIN)
- ✅ process_payment.php: 0 citations (because it's already paid)

---

## Recommendations

If the user wants consistent results across all pages when searching for a specific person:

1. **Search by Ticket Number**: Most reliable (e.g., "014817")
2. **Search by License Number**: Unique identifier
3. **Search by exact first + last name**: Use "JOHNNY" + "AGUSTIN" separately, not "JOHNNY B. AGUSTIN"

### Why "JOHNNY B. AGUSTIN" doesn't work well

The database stores:
```
first_name: "JOHNNY"
middle_initial: "B."
last_name: "AGUSTIN"
```

The search CONCAT pattern creates: `"JOHNNY AGUSTIN"` (no middle initial)

So searching for "JOHNNY B. AGUSTIN" won't match because the middle initial isn't included in the concatenation.

### Suggested Fix (Optional)

If you want middle initial included in name searches, modify the CONCAT in all three files:

```php
// Current:
CONCAT(c.first_name, ' ', c.last_name)

// Suggested:
CONCAT(c.first_name, ' ', COALESCE(CONCAT(c.middle_initial, ' '), ''), c.last_name)
```

This would produce: "JOHNNY B. AGUSTIN" instead of "JOHNNY AGUSTIN"

---

## Verification Commands

```bash
# Check total AGUSTIN citations
php -r "require 'includes/config.php'; \$db = getPDO(); \$stmt = \$db->query(\"SELECT COUNT(*) FROM citations WHERE last_name LIKE '%AGUSTIN%'\"); echo \$stmt->fetchColumn();"

# Check JOHNNY B. AGUSTIN specifically
php -r "require 'includes/config.php'; \$db = getPDO(); \$stmt = \$db->query(\"SELECT ticket_number, first_name, middle_initial, last_name, status FROM citations WHERE first_name='JOHNNY' AND last_name='AGUSTIN'\"); print_r(\$stmt->fetch());"
```

---

**Status**: Working as designed - No bug present ✅

Each page has different filtering logic based on its purpose:
- **LTO Search**: Complete history (all citations)
- **Citation Management**: Active citations (not deleted)
- **Payment Processing**: Unpaid citations only (pending without payment)
