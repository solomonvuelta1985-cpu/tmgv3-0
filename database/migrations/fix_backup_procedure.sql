-- Fix the backup procedure to work with triggers

DELIMITER $$

DROP PROCEDURE IF EXISTS `calculate_next_backup_date`$$

CREATE PROCEDURE `calculate_next_backup_date`()
BEGIN
    DECLARE v_frequency VARCHAR(20);
    DECLARE v_backup_time TIME;
    DECLARE v_next_date DATETIME;
    DECLARE v_base_date DATETIME;

    -- Get current settings
    SELECT backup_frequency, backup_time
    INTO v_frequency, v_backup_time
    FROM backup_settings
    WHERE id = 1;

    -- Use last backup date as base, or current date if no backup yet
    SELECT COALESCE(last_backup_date, NOW())
    INTO v_base_date
    FROM backup_settings
    WHERE id = 1;

    -- Calculate next backup date based on frequency
    CASE v_frequency
        WHEN 'daily' THEN
            SET v_next_date = DATE_ADD(v_base_date, INTERVAL 1 DAY);
        WHEN 'every_3_days' THEN
            SET v_next_date = DATE_ADD(v_base_date, INTERVAL 3 DAY);
        WHEN 'weekly' THEN
            SET v_next_date = DATE_ADD(v_base_date, INTERVAL 1 WEEK);
        WHEN 'monthly' THEN
            SET v_next_date = DATE_ADD(v_base_date, INTERVAL 1 MONTH);
    END CASE;

    -- Set the time component
    SET v_next_date = DATE_ADD(DATE(v_next_date), INTERVAL HOUR(v_backup_time) HOUR);
    SET v_next_date = DATE_ADD(v_next_date, INTERVAL MINUTE(v_backup_time) MINUTE);

    -- If calculated date is in the past, use current date + interval
    IF v_next_date < NOW() THEN
        CASE v_frequency
            WHEN 'daily' THEN
                SET v_next_date = DATE_ADD(NOW(), INTERVAL 1 DAY);
            WHEN 'every_3_days' THEN
                SET v_next_date = DATE_ADD(NOW(), INTERVAL 3 DAY);
            WHEN 'weekly' THEN
                SET v_next_date = DATE_ADD(NOW(), INTERVAL 1 WEEK);
            WHEN 'monthly' THEN
                SET v_next_date = DATE_ADD(NOW(), INTERVAL 1 MONTH);
        END CASE;
        SET v_next_date = DATE_ADD(DATE(v_next_date), INTERVAL HOUR(v_backup_time) HOUR);
        SET v_next_date = DATE_ADD(v_next_date, INTERVAL MINUTE(v_backup_time) MINUTE);
    END IF;

    -- Update the next backup date
    UPDATE backup_settings
    SET next_backup_date = v_next_date
    WHERE id = 1;

    -- Note: No SELECT here to avoid "Not allowed to return a result set from a trigger" error
END$$

DELIMITER ;
