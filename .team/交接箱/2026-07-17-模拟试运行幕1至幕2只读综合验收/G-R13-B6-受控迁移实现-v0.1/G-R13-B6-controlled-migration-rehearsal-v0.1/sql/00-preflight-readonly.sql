-- G-R13-B6 只读预检；不修改数据。
DELIMITER //
DROP PROCEDURE IF EXISTS qms_b6_preflight//
CREATE PROCEDURE qms_b6_preflight()
BEGIN
    IF DATABASE() <> 'jewelry_qms_p0_r13b6' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'B6 target database mismatch';
    END IF;
    IF (SELECT COUNT(*) FROM companies WHERE id = '00000000-0000-0000-0000-000000000001' AND soft_delete = 0) <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'B6 company fingerprint mismatch';
    END IF;
    IF (SELECT COUNT(*) FROM sites WHERE code IN ('PLACE01','PLACE02') AND publish = 1 AND soft_delete = 0) <> 2 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'B6 site fingerprint mismatch';
    END IF;
    IF (SELECT employee_id FROM users WHERE username = 'admin' AND soft_delete = 0 LIMIT 1) <> '00000000-0000-0000-0000-000000000030' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'B6 admin fingerprint mismatch';
    END IF;
    SELECT DATABASE() database_name, 'read_only' mode, 'pass' result;
END//
CALL qms_b6_preflight()//
DROP PROCEDURE qms_b6_preflight//
DELIMITER ;