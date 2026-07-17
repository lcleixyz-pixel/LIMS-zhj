-- G-R13-B5：已确认组织任命的隔离库候选夹具。
-- 只允许在 jewelry_qms_p0_r13b5 执行；不是生产迁移脚本。

DELIMITER //
DROP PROCEDURE IF EXISTS b5_fixture_database_guard//
CREATE PROCEDURE b5_fixture_database_guard()
BEGIN
    IF DATABASE() <> 'jewelry_qms_p0_r13b5' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'G-R13-B5 fixture may only run in jewelry_qms_p0_r13b5';
    END IF;
END//
CALL b5_fixture_database_guard()//
DROP PROCEDURE b5_fixture_database_guard//
DELIMITER ;

SET @company_id = (SELECT id FROM companies WHERE soft_delete = 0 ORDER BY created LIMIT 1);
SET @main_site_id = (
    SELECT id FROM sites
    WHERE name = '乌鲁木齐实验室' AND publish = 1 AND soft_delete = 0
    ORDER BY created LIMIT 1
);
SET @branch_site_id = (
    SELECT id FROM sites
    WHERE name = '和田实验室' AND publish = 1 AND soft_delete = 0
    ORDER BY created LIMIT 1
);

INSERT INTO qms_positions (
    id, company_id, code, name, source, review_status,
    publish, soft_delete, created, modified
) VALUES
    ('b5000000-0000-4000-8000-000000000001', @company_id, 'top_management', '最高管理者', 'b5_confirmed_organization', 'published', 1, 0, NOW(), NOW()),
    ('b5000000-0000-4000-8000-000000000002', @company_id, 'site_quality_coordinator', '场所质量协调人', 'b5_confirmed_organization', 'published', 1, 0, NOW(), NOW()),
    ('b5000000-0000-4000-8000-000000000003', @company_id, 'system_administrator', 'LIMS系统管理员', 'b5_confirmed_organization', 'published', 1, 0, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    source = VALUES(source),
    review_status = 'published',
    publish = 1,
    soft_delete = 0,
    modified = NOW();

INSERT INTO employees (
    id, company_id, primary_site_id, employee_number, name,
    publish, soft_delete, created, modified
)
SELECT
    'b5000000-0000-4000-8000-000000000101',
    @company_id,
    @branch_site_id,
    'B5-CAND-RUZE',
    '如则托合提',
    1, 0, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM employees WHERE name = '如则托合提' AND soft_delete = 0
);

INSERT INTO employees (
    id, company_id, primary_site_id, employee_number, name,
    publish, soft_delete, created, modified
)
SELECT
    'b5000000-0000-4000-8000-000000000102',
    @company_id,
    @branch_site_id,
    'B5-CAND-MIERBULA',
    '米尔布拉',
    1, 0, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM employees WHERE name = '米尔布拉' AND soft_delete = 0
);

-- 现行 admin 指向已软删除的张晓磊旧员工行；候选环境改为在用员工行。
SET @active_zhang_id = (
    SELECT id FROM employees
    WHERE name = '张晓磊' AND publish = 1 AND soft_delete = 0
    ORDER BY modified DESC LIMIT 1
);
UPDATE users
SET employee_id = @active_zhang_id, modified = NOW()
WHERE username = 'admin' AND soft_delete = 0;

INSERT INTO employee_appointments (
    id, company_id, employee_id, position_id, site_id,
    appointment_key, appointment_type, position_name, appointment_scope,
    appointed_at, source_document_number, source_excerpt, source_kind,
    status, publish, soft_delete, created, modified
)
SELECT
    UUID(),
    @company_id,
    e.id,
    p.id,
    s.id,
    target.appointment_key,
    target.appointment_type,
    p.name,
    target.appointment_scope,
    '2026-07-17',
    'B5-CONFIRMED-20260717',
    '质量负责人于 2026-07-17 确认的组织、岗位、场所和授权关系；正式迁移前需形成受控任命依据。',
    'corporate_evidence',
    'active',
    1,
    0,
    NOW(),
    NOW()
FROM (
    SELECT 'b5-candidate-yu-general-manager' appointment_key, '俞炳星' employee_name, 'company_general_manager' position_code, NULL site_code, 'role' appointment_type, '总经理' appointment_scope
    UNION ALL SELECT 'b5-candidate-yu-top-management', '俞炳星', 'top_management', NULL, 'role', 'CMA 登记最高管理者'
    UNION ALL SELECT 'b5-candidate-yu-signatory', '俞炳星', 'authorized_signatory', NULL, 'authorization', '两场所灵活轮调授权签字人'
    UNION ALL SELECT 'b5-candidate-yu-auditor', '俞炳星', 'internal_auditor', NULL, 'role', '内审组员候选'
    UNION ALL SELECT 'b5-candidate-yu-supervisor-branch', '俞炳星', 'supervisor', 'PLACE02', 'role', '和田实验室监督工作'

    UNION ALL SELECT 'b5-candidate-zhang-quality', '张晓磊', 'quality_manager', NULL, 'role', '质量负责人'
    UNION ALL SELECT 'b5-candidate-zhang-top-management', '张晓磊', 'top_management', NULL, 'role', '内部最高管理者；不替代 CMA 登记最高管理者'
    UNION ALL SELECT 'b5-candidate-zhang-signatory', '张晓磊', 'authorized_signatory', NULL, 'authorization', '两场所灵活轮调授权签字人'
    UNION ALL SELECT 'b5-candidate-zhang-audit-lead', '张晓磊', 'internal_auditor', NULL, 'role', '通常担任内审组长'
    UNION ALL SELECT 'b5-candidate-zhang-supervisor-main', '张晓磊', 'supervisor', 'PLACE01', 'role', '乌鲁木齐实验室监督工作'
    UNION ALL SELECT 'b5-candidate-zhang-system-admin', '张晓磊', 'system_administrator', NULL, 'responsibility', 'LIMS 系统管理员'

    UNION ALL SELECT 'b5-candidate-liu-site-quality', '刘恒春', 'site_quality_coordinator', NULL, 'role', '质量负责人代理/两场所质量协调；代理启用需另行受控记录'
    UNION ALL SELECT 'b5-candidate-liu-overall-technical', '刘恒春', 'technical_manager', NULL, 'role', '总体技术责任人'
    UNION ALL SELECT 'b5-candidate-liu-signatory-main', '刘恒春', 'authorized_signatory', 'PLACE01', 'authorization', '乌鲁木齐授权签字人'
    UNION ALL SELECT 'b5-candidate-liu-signatory-branch', '刘恒春', 'authorized_signatory', 'PLACE02', 'authorization', '和田授权签字人'
    UNION ALL SELECT 'b5-candidate-liu-auditor', '刘恒春', 'internal_auditor', NULL, 'role', '内审组员候选'

    UNION ALL SELECT 'b5-candidate-cao-technical-main', '曹红', 'technical_manager', 'PLACE01', 'role', '乌鲁木齐技术负责人'
    UNION ALL SELECT 'b5-candidate-cao-signatory-main', '曹红', 'authorized_signatory', 'PLACE01', 'authorization', '乌鲁木齐固定授权签字人'
    UNION ALL SELECT 'b5-candidate-cao-auditor', '曹红', 'internal_auditor', NULL, 'role', '内审组员候选'

    UNION ALL SELECT 'b5-candidate-li-technical-branch', '李成辉', 'technical_manager', 'PLACE02', 'role', '和田技术负责人'
    UNION ALL SELECT 'b5-candidate-li-signatory-branch', '李成辉', 'authorized_signatory', 'PLACE02', 'authorization', '和田固定授权签字人'

    UNION ALL SELECT 'b5-candidate-fu-document-main', '付丽', 'document_controller', 'PLACE01', 'role', '乌鲁木齐文件管理员'
    UNION ALL SELECT 'b5-candidate-wang-equipment-main', '王胜林', 'equipment_manager', 'PLACE01', 'role', '乌鲁木齐设备管理员'
    UNION ALL SELECT 'b5-candidate-ruze-document-branch', '如则托合提', 'document_controller', 'PLACE02', 'role', '和田文件管理员；“如则”姓名对应待正式确认'
    UNION ALL SELECT 'b5-candidate-mierbula-equipment-branch', '米尔布拉', 'equipment_manager', 'PLACE02', 'role', '和田设备管理员'
) AS target
JOIN employees e
    ON e.name = target.employee_name AND e.publish = 1 AND e.soft_delete = 0
JOIN qms_positions p
    ON p.code = target.position_code AND p.publish = 1 AND p.soft_delete = 0
LEFT JOIN sites s
    ON s.code = target.site_code AND s.publish = 1 AND s.soft_delete = 0
ON DUPLICATE KEY UPDATE
    employee_id = VALUES(employee_id),
    position_id = VALUES(position_id),
    site_id = VALUES(site_id),
    appointment_type = VALUES(appointment_type),
    position_name = VALUES(position_name),
    appointment_scope = VALUES(appointment_scope),
    appointed_at = VALUES(appointed_at),
    source_document_number = VALUES(source_document_number),
    source_excerpt = VALUES(source_excerpt),
    source_kind = VALUES(source_kind),
    status = 'active',
    publish = 1,
    soft_delete = 0,
    modified = NOW();
