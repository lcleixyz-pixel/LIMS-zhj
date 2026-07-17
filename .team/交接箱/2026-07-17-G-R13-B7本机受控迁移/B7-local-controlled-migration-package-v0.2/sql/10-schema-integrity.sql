-- G-R13-B2：编号与 CAPA 来源链唯一约束。
-- 必须先执行 qms:p0-preflight；本迁移若发现重复数据会主动阻断，不修改业务记录。

DELIMITER //

DROP PROCEDURE IF EXISTS qms_apply_p0_record_integrity//
CREATE PROCEDURE qms_apply_p0_record_integrity()
BEGIN
    DECLARE blocking_count INT DEFAULT 0;

    SELECT
        (SELECT COUNT(*) FROM (
            SELECT 1 FROM customer_complaints
            GROUP BY company_id, complaint_number HAVING COUNT(*) > 1
        ) duplicate_complaints)
        + (SELECT COUNT(*) FROM (
            SELECT 1 FROM capas
            GROUP BY company_id, capa_number HAVING COUNT(*) > 1
        ) duplicate_capas)
        + (SELECT COUNT(*) FROM (
            SELECT 1 FROM nonconformities
            GROUP BY company_id, nc_number HAVING COUNT(*) > 1
        ) duplicate_ncs)
        + (SELECT COUNT(*) FROM (
            SELECT 1 FROM capas
            WHERE source_type IS NOT NULL AND source_record_id IS NOT NULL
            GROUP BY company_id, source_type, source_record_id HAVING COUNT(*) > 1
        ) duplicate_sources)
    INTO blocking_count;

    IF blocking_count > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'P0 preflight blocked: duplicate numbers or CAPA source links exist';
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_complaints'
          AND INDEX_NAME = 'uq_complaint_company_number'
    ) THEN
        ALTER TABLE customer_complaints
            ADD UNIQUE KEY uq_complaint_company_number (company_id, complaint_number);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'capas'
          AND INDEX_NAME = 'uq_capa_company_number'
    ) THEN
        ALTER TABLE capas
            ADD UNIQUE KEY uq_capa_company_number (company_id, capa_number);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nonconformities'
          AND INDEX_NAME = 'uq_nc_company_number'
    ) THEN
        ALTER TABLE nonconformities
            ADD UNIQUE KEY uq_nc_company_number (company_id, nc_number);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'capas'
          AND INDEX_NAME = 'uq_capa_company_source_record'
    ) THEN
        ALTER TABLE capas
            ADD UNIQUE KEY uq_capa_company_source_record
                (company_id, source_type, source_record_id);
    END IF;
END//

CALL qms_apply_p0_record_integrity()//
DROP PROCEDURE qms_apply_p0_record_integrity//

DELIMITER ;
