# Database Migration: Add Category to Violations

## Overview
This migration adds a `category` column to the `violation_types` table, allowing violations to be organized into categories (Helmet, License, Vehicle, Driving, Traffic, Misc, Other).

## How to Run the Migration

### Option 1: Using phpMyAdmin (Recommended)
1. Open phpMyAdmin in your browser: `http://localhost/phpmyadmin`
2. Select the `traffic_system` database from the left sidebar
3. Click on the **SQL** tab at the top
4. Copy and paste the contents of `add_category_to_violations.sql` into the SQL textarea
5. Click **Go** to execute the migration
6. You should see "Query executed successfully" message

### Option 2: Using MySQL Command Line
1. Open Command Prompt as Administrator
2. Navigate to the XAMPP MySQL bin directory:
   ```cmd
   cd c:\xampp\mysql\bin
   ```
3. Run the migration:
   ```cmd
   mysql -u root traffic_system < "c:\xampp\htdocs\tmg\database\migrations\add_category_to_violations.sql"
   ```

### Option 3: Manual Execution (If migration file fails)
If the automated migration doesn't work, run these SQL commands manually in phpMyAdmin:

```sql
-- Add category column
ALTER TABLE violation_types
ADD COLUMN category VARCHAR(50) NULL DEFAULT 'Other' AFTER description;

-- Create index
ALTER TABLE violation_types
ADD INDEX idx_category (category);

-- Auto-categorize existing violations
UPDATE violation_types SET category = 'Helmet' WHERE violation_type LIKE '%HELMET%';
UPDATE violation_types SET category = 'License' WHERE violation_type LIKE '%LICENSE%' OR violation_type LIKE '%REGISTRATION%' OR violation_type LIKE '%OPLAN VISA%' OR violation_type LIKE '%E-OV MATCH%';
UPDATE violation_types SET category = 'Vehicle' WHERE violation_type LIKE '%DEFECTIVE%' OR violation_type LIKE '%MUFFLER%' OR violation_type LIKE '%MODIFICATION%' OR violation_type LIKE '%PARTS%';
UPDATE violation_types SET category = 'Driving' WHERE violation_type LIKE '%RECKLESS%' OR violation_type LIKE '%DRAG RACING%' OR violation_type LIKE '%DRUNK%' OR violation_type LIKE '%DRIVING IN SHORT%' OR violation_type LIKE '%ARROGANT%';
UPDATE violation_types SET category = 'Traffic' WHERE violation_type LIKE '%TRAFFIC SIGN%' OR violation_type LIKE '%PARKING%' OR violation_type LIKE '%OBSTRUCTION%' OR violation_type LIKE '%PEDESTRIAN%' OR violation_type LIKE '%LOADING%' OR violation_type LIKE '%PASSENGER ON TOP%';
UPDATE violation_types SET category = 'Misc' WHERE violation_type LIKE '%COLORUM%' OR violation_type LIKE '%TRASHBIN%' OR violation_type LIKE '%OVERLOADED%' OR violation_type LIKE '%CHARGING%' OR violation_type LIKE '%REFUSAL%';
```

## Verify Migration

After running the migration, verify it was successful:

1. Go to phpMyAdmin
2. Select `traffic_system` database
3. Click on `violation_types` table
4. Click on **Structure** tab
5. You should see a new column called `category` with type `VARCHAR(50)`
6. Click on **Browse** tab to see that existing violations have been categorized

## What Changed

### Database Changes
- Added `category` column to `violation_types` table
- Added index on `category` for better performance
- Auto-categorized all existing violations based on their names

### Application Changes
- **Admin Panel** (`admin/violations.php`):
  - Added category dropdown when creating/editing violations
  - Added category column in violations table with color-coded badges

- **API Endpoints**:
  - Updated `api/violation_save.php` to save category
  - Updated `api/violation_update.php` to update category

### Available Categories
1. **Helmet** - All helmet-related violations
2. **License** - License, registration, and documentation violations
3. **Vehicle** - Vehicle defects and modifications
4. **Driving** - Reckless driving, drunk driving, etc.
5. **Traffic** - Traffic signs, parking, obstruction
6. **Misc** - Miscellaneous violations (colorum, overloaded, etc.)
7. **Other** - Any other violations

## Troubleshooting

### Error: "Duplicate column name 'category'"
This means the column already exists. You can skip the migration or verify the column exists by checking the table structure.

### Error: "Access denied"
Make sure you're using the correct database credentials. The default XAMPP setup uses:
- Username: `root`
- Password: (empty)

### Categories not showing in admin panel
1. Make sure the migration ran successfully
2. Clear your browser cache and reload the page
3. Check the browser console for any JavaScript errors

## Need Help?
If you encounter any issues, check:
1. XAMPP is running (Apache and MySQL)
2. Database name is correct (`traffic_system`)
3. All files are in the correct locations
