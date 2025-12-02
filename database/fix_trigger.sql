-- Fix: Remove problematic trigger and replace with BEFORE INSERT trigger
-- The AFTER INSERT trigger can't update the same violations table

-- Drop the problematic trigger
DROP TRIGGER IF EXISTS after_violation_insert;

-- Create a BEFORE INSERT trigger that sets fine_amount before insertion
DELIMITER //

CREATE TRIGGER before_violation_insert
BEFORE INSERT ON violations
FOR EACH ROW
BEGIN
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
END //

DELIMITER ;

-- Create separate trigger to update citation total (this is safe)
DELIMITER //

CREATE TRIGGER after_violation_insert_update_total
AFTER INSERT ON violations
FOR EACH ROW
BEGIN
    -- Update total fine on citation (different table, so this is OK)
    UPDATE citations
    SET total_fine = (SELECT COALESCE(SUM(fine_amount), 0) FROM violations WHERE citation_id = NEW.citation_id)
    WHERE citation_id = NEW.citation_id;
END //

DELIMITER ;
