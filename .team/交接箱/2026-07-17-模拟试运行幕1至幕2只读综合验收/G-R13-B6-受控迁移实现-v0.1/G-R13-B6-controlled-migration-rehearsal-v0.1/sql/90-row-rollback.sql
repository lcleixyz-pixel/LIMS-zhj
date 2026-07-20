-- G-R13-B6 行级回退；只按本包固定键和 before-state 回退。
DELIMITER //
DROP PROCEDURE IF EXISTS qms_b6_rollback_organization//
CREATE PROCEDURE qms_b6_rollback_organization()
BEGIN
    IF DATABASE() <> 'jewelry_qms_p0_r13b6' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'B6 target database mismatch';
    END IF;
    START TRANSACTION;
    DELETE FROM employee_appointments WHERE appointment_key IN ('organization:E000:company_general_manager:GLOBAL','organization:E000:top_management:GLOBAL','organization:E000:authorized_signatory:GLOBAL','organization:E000:internal_auditor:GLOBAL','organization:E000:supervisor:PLACE02','organization:E002:quality_manager:GLOBAL','organization:E002:top_management:GLOBAL','organization:E002:authorized_signatory:GLOBAL','organization:E002:internal_auditor:GLOBAL','organization:E002:supervisor:PLACE01','organization:E002:system_administrator:GLOBAL','organization:E005:site_quality_coordinator:GLOBAL','organization:E005:technical_manager:GLOBAL','organization:E005:authorized_signatory:PLACE01','organization:E005:authorized_signatory:PLACE02','organization:E005:internal_auditor:GLOBAL','organization:E003:technical_manager:PLACE01','organization:E003:authorized_signatory:PLACE01','organization:E003:internal_auditor:GLOBAL','organization:E004:technical_manager:PLACE02','organization:E004:authorized_signatory:PLACE02','organization:E006:document_controller:PLACE01','organization:E010:equipment_manager:PLACE01','organization:E012:document_controller:PLACE02','organization:E013:equipment_manager:PLACE02');
    UPDATE users SET employee_id = '00000000-0000-0000-0000-000000000030', modified = NOW()
    WHERE username = 'admin' AND soft_delete = 0;
    DELETE FROM employees
    WHERE id IN ('2645796a-c7c4-46ee-8d3d-5f288ef564bf','cf9ed166-8540-4d2e-8aa4-7eb7653b5a72')
      AND NOT EXISTS (SELECT 1 FROM users u WHERE u.employee_id = employees.id)
      AND NOT EXISTS (SELECT 1 FROM employee_appointments ea WHERE ea.employee_id = employees.id);
    DELETE FROM qms_positions
    WHERE code IN ('top_management','system_administrator','site_quality_coordinator')
      AND NOT EXISTS (SELECT 1 FROM employee_appointments ea WHERE ea.position_id = qms_positions.id);
    COMMIT;
END//
CALL qms_b6_rollback_organization()//
DROP PROCEDURE qms_b6_rollback_organization//
DELIMITER ;