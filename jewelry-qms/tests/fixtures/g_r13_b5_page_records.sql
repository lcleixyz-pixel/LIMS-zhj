-- G-R13-B5：页面验收账号与记录夹具。
-- 只允许在 jewelry_qms_p0_r13b5 执行；所有账号和记录均以 B5- 标识。

DELIMITER //
DROP PROCEDURE IF EXISTS b5_page_fixture_database_guard//
CREATE PROCEDURE b5_page_fixture_database_guard()
BEGIN
    IF DATABASE() <> 'jewelry_qms_p0_r13b5' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'G-R13-B5 page fixture may only run in jewelry_qms_p0_r13b5';
    END IF;
END//
CALL b5_page_fixture_database_guard()//
DROP PROCEDURE b5_page_fixture_database_guard//
DELIMITER ;

SET @company_id = (SELECT id FROM companies WHERE soft_delete = 0 ORDER BY created LIMIT 1);
SET @main_site_id = (SELECT id FROM sites WHERE code = 'PLACE01' AND soft_delete = 0 LIMIT 1);
SET @branch_site_id = (SELECT id FROM sites WHERE code = 'PLACE02' AND soft_delete = 0 LIMIT 1);
SET @password_hash = '$2y$12$LdSfrbOit8uAIygYGWmfvOHXyuYZt6vXIiM8kvdktPcfkKBw14VJu';

INSERT INTO users (
    id, company_id, employee_id, username, password, name, role,
    publish, soft_delete, created, modified
)
SELECT target.user_id, @company_id, e.id, target.username, @password_hash, e.name, target.role,
       1, 0, NOW(), NOW()
FROM (
    SELECT 'b5000000-0000-4000-8000-000000000201' user_id, '俞炳星' employee_name, 'b5_yu' username, 'auditor' role
    UNION ALL SELECT 'b5000000-0000-4000-8000-000000000202', '张晓磊', 'b5_zhang', 'admin'
    UNION ALL SELECT 'b5000000-0000-4000-8000-000000000203', '刘恒春', 'b5_liu', 'department_head'
    UNION ALL SELECT 'b5000000-0000-4000-8000-000000000204', '曹红', 'b5_cao', 'department_head'
    UNION ALL SELECT 'b5000000-0000-4000-8000-000000000205', '李成辉', 'b5_li', 'department_head'
    UNION ALL SELECT 'b5000000-0000-4000-8000-000000000206', '付丽', 'b5_fu', 'staff'
    UNION ALL SELECT 'b5000000-0000-4000-8000-000000000207', '王胜林', 'b5_wang', 'department_head'
    UNION ALL SELECT 'b5000000-0000-4000-8000-000000000208', '如则托合提', 'b5_ruze', 'staff'
    UNION ALL SELECT 'b5000000-0000-4000-8000-000000000209', '米尔布拉', 'b5_mierbula', 'department_head'
) AS target
JOIN employees e
    ON e.name = target.employee_name AND e.publish = 1 AND e.soft_delete = 0
ON DUPLICATE KEY UPDATE
    employee_id = VALUES(employee_id),
    password = VALUES(password),
    name = VALUES(name),
    role = VALUES(role),
    publish = 1,
    soft_delete = 0,
    modified = NOW();

INSERT INTO customer_complaints (
    id, company_id, complaint_number, customer_name, received_date,
    description, assigned_to, status, publish, soft_delete, record_status,
    created, modified, created_by
) VALUES
    (
        'b5000000-0000-4000-8000-000000000701', @company_id,
        'CP2026991', 'B5 页面验收客户', '2026-07-17',
        '乌鲁木齐投诉权限页面验收', 'b5000000-0000-4000-8000-000000000203',
        'received', 1, 0, 0, NOW(), NOW(),
        'b5000000-0000-4000-8000-000000000206'
    ),
    (
        'b5000000-0000-4000-8000-000000000702', @company_id,
        'CP2026992', 'B5 页面验收客户', '2026-07-17',
        '和田投诉权限页面验收', 'b5000000-0000-4000-8000-000000000203',
        'received', 1, 0, 0, NOW(), NOW(),
        'b5000000-0000-4000-8000-000000000208'
    )
ON DUPLICATE KEY UPDATE
    complaint_number = VALUES(complaint_number),
    description = VALUES(description),
    assigned_to = VALUES(assigned_to),
    status = 'received',
    soft_delete = 0,
    modified = NOW(),
    created_by = VALUES(created_by);

INSERT INTO equipments (
    id, company_id, equipment_number, name, site_id,
    calibration_required, status, publish, soft_delete,
    created, modified, created_by
) VALUES
    (
        'b5000000-0000-4000-8000-000000000711', @company_id,
        'B5-MAIN-EQ', 'B5 乌鲁木齐设备', @main_site_id,
        1, 'active', 1, 0, NOW(), NOW(),
        'b5000000-0000-4000-8000-000000000207'
    ),
    (
        'b5000000-0000-4000-8000-000000000712', @company_id,
        'B5-BRANCH-EQ', 'B5 和田设备', @branch_site_id,
        1, 'active', 1, 0, NOW(), NOW(),
        'b5000000-0000-4000-8000-000000000209'
    )
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    site_id = VALUES(site_id),
    status = 'active',
    soft_delete = 0,
    modified = NOW(),
    created_by = VALUES(created_by);

INSERT INTO competency_records (
    id, company_id, employee_id, test_item, method_standard,
    assessment_date, assessor_id, result, publish, soft_delete,
    created, modified
)
SELECT
    'b5000000-0000-4000-8000-000000000721', @company_id, e.id,
    'B5 乌鲁木齐能力确认', 'B5-METHOD', '2026-07-17',
    z.id, 'pending', 1, 0, NOW(), NOW()
FROM employees e
JOIN employees z ON z.name = '张晓磊' AND z.soft_delete = 0
WHERE e.name = '王胜林' AND e.soft_delete = 0
ON DUPLICATE KEY UPDATE
    employee_id = VALUES(employee_id),
    result = 'pending',
    soft_delete = 0,
    modified = NOW();

INSERT INTO competency_records (
    id, company_id, employee_id, test_item, method_standard,
    assessment_date, assessor_id, result, publish, soft_delete,
    created, modified
)
SELECT
    'b5000000-0000-4000-8000-000000000722', @company_id, e.id,
    'B5 和田能力确认', 'B5-METHOD', '2026-07-17',
    z.id, 'pending', 1, 0, NOW(), NOW()
FROM employees e
JOIN employees z ON z.name = '张晓磊' AND z.soft_delete = 0
WHERE e.name = '李成辉' AND e.soft_delete = 0
ON DUPLICATE KEY UPDATE
    employee_id = VALUES(employee_id),
    result = 'pending',
    soft_delete = 0,
    modified = NOW();

INSERT INTO capas (
    id, company_id, capa_number, description, assigned_to, status,
    publish, soft_delete, record_status, created, modified, created_by
) VALUES (
    'b5000000-0000-4000-8000-000000000731', @company_id,
    'CAPA2026991', 'B5 CAPA 责任人与验证权限页面验收',
    'b5000000-0000-4000-8000-000000000206', 'implementing',
    1, 0, 0, NOW(), NOW(),
    'b5000000-0000-4000-8000-000000000202'
)
ON DUPLICATE KEY UPDATE
    capa_number = VALUES(capa_number),
    assigned_to = VALUES(assigned_to),
    status = 'implementing',
    soft_delete = 0,
    modified = NOW();

INSERT INTO record_form_templates (
    id, company_id, doc_number, name, module, print_template_key,
    field_schema, version, status, review_status, publish, soft_delete,
    created, modified, created_by
) VALUES (
    'b5000000-0000-4000-8000-000000000741', @company_id,
    'B5-TPL-01', 'B5 岗位动作验收模板', 'quality',
    'record_form_default', '[]', 'A/0', 'draft', 'pending',
    1, 0, NOW(), NOW(),
    'b5000000-0000-4000-8000-000000000202'
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    status = 'draft',
    review_status = 'pending',
    soft_delete = 0,
    modified = NOW();
