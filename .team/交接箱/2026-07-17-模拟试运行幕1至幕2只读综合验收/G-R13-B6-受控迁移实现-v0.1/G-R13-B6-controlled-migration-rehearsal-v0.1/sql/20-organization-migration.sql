-- G-R13-B6 组织数据迁移；只允许在清单指定数据库执行。
DELIMITER //
DROP PROCEDURE IF EXISTS qms_b6_apply_organization//
CREATE PROCEDURE qms_b6_apply_organization()
BEGIN
    IF DATABASE() <> 'jewelry_qms_p0_r13b6' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'B6 target database mismatch';
    END IF;
    START TRANSACTION;

    INSERT INTO employees (id, company_id, primary_site_id, employee_number, name, publish, soft_delete, created, modified)
    SELECT '2645796a-c7c4-46ee-8d3d-5f288ef564bf', '00000000-0000-0000-0000-000000000001', 'c7264cf5-873e-4300-a9fc-866047c45879', 'E012', '如则托合提', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM employees WHERE (id = '2645796a-c7c4-46ee-8d3d-5f288ef564bf' OR employee_number = 'E012' OR name = '如则托合提') AND soft_delete = 0);

    INSERT INTO employees (id, company_id, primary_site_id, employee_number, name, publish, soft_delete, created, modified)
    SELECT 'cf9ed166-8540-4d2e-8aa4-7eb7653b5a72', '00000000-0000-0000-0000-000000000001', 'c7264cf5-873e-4300-a9fc-866047c45879', 'E013', '米尔布拉', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM employees WHERE (id = 'cf9ed166-8540-4d2e-8aa4-7eb7653b5a72' OR employee_number = 'E013' OR name = '米尔布拉') AND soft_delete = 0);

    INSERT INTO qms_positions (id, company_id, code, name, source, review_status, publish, soft_delete, created, modified)
    SELECT '2d53985a-fcee-4dcf-899e-f87e85c85b95', '00000000-0000-0000-0000-000000000001', 'company_general_manager', '总经理', 'controlled_migration', 'published', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM qms_positions WHERE code = 'company_general_manager' AND publish = 1 AND soft_delete = 0);

    INSERT INTO qms_positions (id, company_id, code, name, source, review_status, publish, soft_delete, created, modified)
    SELECT '3c5bd8eb-ebff-4f82-87b2-c6fbed2d0809', '00000000-0000-0000-0000-000000000001', 'top_management', '最高管理者', 'controlled_migration', 'published', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM qms_positions WHERE code = 'top_management' AND publish = 1 AND soft_delete = 0);

    INSERT INTO qms_positions (id, company_id, code, name, source, review_status, publish, soft_delete, created, modified)
    SELECT 'beeb2e72-721c-4071-8a57-0300b1503043', '00000000-0000-0000-0000-000000000001', 'authorized_signatory', '授权签字人', 'controlled_migration', 'published', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM qms_positions WHERE code = 'authorized_signatory' AND publish = 1 AND soft_delete = 0);

    INSERT INTO qms_positions (id, company_id, code, name, source, review_status, publish, soft_delete, created, modified)
    SELECT '9f3f1ae7-d2e7-4ecd-8463-0dfd39a05f3c', '00000000-0000-0000-0000-000000000001', 'internal_auditor', '内审员', 'controlled_migration', 'published', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM qms_positions WHERE code = 'internal_auditor' AND publish = 1 AND soft_delete = 0);

    INSERT INTO qms_positions (id, company_id, code, name, source, review_status, publish, soft_delete, created, modified)
    SELECT 'fc173a2a-8684-4586-82e2-cc2b3f74c51a', '00000000-0000-0000-0000-000000000001', 'supervisor', '监督员', 'controlled_migration', 'published', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM qms_positions WHERE code = 'supervisor' AND publish = 1 AND soft_delete = 0);

    INSERT INTO qms_positions (id, company_id, code, name, source, review_status, publish, soft_delete, created, modified)
    SELECT 'ee3b3fe5-e6bb-4b2d-898f-dbad1c286c20', '00000000-0000-0000-0000-000000000001', 'quality_manager', '质量负责人', 'controlled_migration', 'published', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM qms_positions WHERE code = 'quality_manager' AND publish = 1 AND soft_delete = 0);

    INSERT INTO qms_positions (id, company_id, code, name, source, review_status, publish, soft_delete, created, modified)
    SELECT '558e44a1-00e6-4c82-861b-4abc18947b3d', '00000000-0000-0000-0000-000000000001', 'system_administrator', 'LIMS系统管理员', 'controlled_migration', 'published', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM qms_positions WHERE code = 'system_administrator' AND publish = 1 AND soft_delete = 0);

    INSERT INTO qms_positions (id, company_id, code, name, source, review_status, publish, soft_delete, created, modified)
    SELECT 'b404da2d-72af-4d65-8c4c-be87a708b37f', '00000000-0000-0000-0000-000000000001', 'site_quality_coordinator', '场所质量协调人', 'controlled_migration', 'published', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM qms_positions WHERE code = 'site_quality_coordinator' AND publish = 1 AND soft_delete = 0);

    INSERT INTO qms_positions (id, company_id, code, name, source, review_status, publish, soft_delete, created, modified)
    SELECT '43763b00-6ba7-4b2a-8938-234f6e375168', '00000000-0000-0000-0000-000000000001', 'technical_manager', '技术负责人', 'controlled_migration', 'published', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM qms_positions WHERE code = 'technical_manager' AND publish = 1 AND soft_delete = 0);

    INSERT INTO qms_positions (id, company_id, code, name, source, review_status, publish, soft_delete, created, modified)
    SELECT 'bad7a178-baa9-4f28-82bd-a198c802b0ac', '00000000-0000-0000-0000-000000000001', 'document_controller', '文件管理员', 'controlled_migration', 'published', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM qms_positions WHERE code = 'document_controller' AND publish = 1 AND soft_delete = 0);

    INSERT INTO qms_positions (id, company_id, code, name, source, review_status, publish, soft_delete, created, modified)
    SELECT '446bb2ff-3990-4a5f-8331-99cd15b957d1', '00000000-0000-0000-0000-000000000001', 'equipment_manager', '设备管理员', 'controlled_migration', 'published', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM qms_positions WHERE code = 'equipment_manager' AND publish = 1 AND soft_delete = 0);

    INSERT INTO employee_appointments (
        id, company_id, employee_id, position_id, site_id, appointment_key,
        appointment_type, position_name, appointment_scope, appointed_at,
        source_document_number, source_excerpt, source_kind, status,
        publish, soft_delete, created, modified
    )
    SELECT '6c03ef59-74b7-4260-841a-51f04be6bae7', '00000000-0000-0000-0000-000000000001',
        (SELECT id FROM employees WHERE employee_number = 'E000' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM qms_positions WHERE code = 'company_general_manager' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        NULL, 'organization:E000:company_general_manager:GLOBAL', 'role', '总经理', '总经理', '2026-07-17', 'ORG-APPOINT-2026-01', '经人工确认的人员、岗位、场所和授权关系，仅用于 B6 隔离迁移演练。', 'corporate_evidence', 'active', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM employee_appointments WHERE appointment_key = 'organization:E000:company_general_manager:GLOBAL' AND soft_delete = 0);

    INSERT INTO employee_appointments (
        id, company_id, employee_id, position_id, site_id, appointment_key,
        appointment_type, position_name, appointment_scope, appointed_at,
        source_document_number, source_excerpt, source_kind, status,
        publish, soft_delete, created, modified
    )
    SELECT '0f2dc951-e603-46c8-8153-251ceab1633f', '00000000-0000-0000-0000-000000000001',
        (SELECT id FROM employees WHERE employee_number = 'E000' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM qms_positions WHERE code = 'top_management' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        NULL, 'organization:E000:top_management:GLOBAL', 'role', '最高管理者', 'CMA 登记最高管理者', '2026-07-17', 'ORG-APPOINT-2026-01', '经人工确认的人员、岗位、场所和授权关系，仅用于 B6 隔离迁移演练。', 'corporate_evidence', 'active', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM employee_appointments WHERE appointment_key = 'organization:E000:top_management:GLOBAL' AND soft_delete = 0);

    INSERT INTO employee_appointments (
        id, company_id, employee_id, position_id, site_id, appointment_key,
        appointment_type, position_name, appointment_scope, appointed_at,
        source_document_number, source_excerpt, source_kind, status,
        publish, soft_delete, created, modified
    )
    SELECT 'ff8162af-a44c-4a3c-857b-756b7b19c81d', '00000000-0000-0000-0000-000000000001',
        (SELECT id FROM employees WHERE employee_number = 'E000' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM qms_positions WHERE code = 'authorized_signatory' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        NULL, 'organization:E000:authorized_signatory:GLOBAL', 'authorization', '授权签字人', '两场所灵活轮调授权签字人', '2026-07-17', 'ORG-APPOINT-2026-01', '经人工确认的人员、岗位、场所和授权关系，仅用于 B6 隔离迁移演练。', 'corporate_evidence', 'active', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM employee_appointments WHERE appointment_key = 'organization:E000:authorized_signatory:GLOBAL' AND soft_delete = 0);

    INSERT INTO employee_appointments (
        id, company_id, employee_id, position_id, site_id, appointment_key,
        appointment_type, position_name, appointment_scope, appointed_at,
        source_document_number, source_excerpt, source_kind, status,
        publish, soft_delete, created, modified
    )
    SELECT '6e21f91a-1253-4ad0-8ee7-e8ad1b59198b', '00000000-0000-0000-0000-000000000001',
        (SELECT id FROM employees WHERE employee_number = 'E000' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM qms_positions WHERE code = 'internal_auditor' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        NULL, 'organization:E000:internal_auditor:GLOBAL', 'role', '内审员', '内审组员候选', '2026-07-17', 'ORG-APPOINT-2026-01', '经人工确认的人员、岗位、场所和授权关系，仅用于 B6 隔离迁移演练。', 'corporate_evidence', 'active', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM employee_appointments WHERE appointment_key = 'organization:E000:internal_auditor:GLOBAL' AND soft_delete = 0);

    INSERT INTO employee_appointments (
        id, company_id, employee_id, position_id, site_id, appointment_key,
        appointment_type, position_name, appointment_scope, appointed_at,
        source_document_number, source_excerpt, source_kind, status,
        publish, soft_delete, created, modified
    )
    SELECT '66df0de5-f881-4af3-8bd3-d9ff17015d22', '00000000-0000-0000-0000-000000000001',
        (SELECT id FROM employees WHERE employee_number = 'E000' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM qms_positions WHERE code = 'supervisor' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM sites WHERE code = 'PLACE02' AND soft_delete = 0 LIMIT 1), 'organization:E000:supervisor:PLACE02', 'role', '监督员', '和田实验室监督工作', '2026-07-17', 'ORG-APPOINT-2026-01', '经人工确认的人员、岗位、场所和授权关系，仅用于 B6 隔离迁移演练。', 'corporate_evidence', 'active', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM employee_appointments WHERE appointment_key = 'organization:E000:supervisor:PLACE02' AND soft_delete = 0);

    INSERT INTO employee_appointments (
        id, company_id, employee_id, position_id, site_id, appointment_key,
        appointment_type, position_name, appointment_scope, appointed_at,
        source_document_number, source_excerpt, source_kind, status,
        publish, soft_delete, created, modified
    )
    SELECT '8a96f080-dc50-4a36-87cc-9e97a3c3c2fe', '00000000-0000-0000-0000-000000000001',
        (SELECT id FROM employees WHERE employee_number = 'E002' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM qms_positions WHERE code = 'quality_manager' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        NULL, 'organization:E002:quality_manager:GLOBAL', 'role', '质量负责人', '质量负责人', '2026-07-17', 'ORG-APPOINT-2026-01', '经人工确认的人员、岗位、场所和授权关系，仅用于 B6 隔离迁移演练。', 'corporate_evidence', 'active', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM employee_appointments WHERE appointment_key = 'organization:E002:quality_manager:GLOBAL' AND soft_delete = 0);

    INSERT INTO employee_appointments (
        id, company_id, employee_id, position_id, site_id, appointment_key,
        appointment_type, position_name, appointment_scope, appointed_at,
        source_document_number, source_excerpt, source_kind, status,
        publish, soft_delete, created, modified
    )
    SELECT '002f17d8-21e2-4b14-8149-fca7b21ef31b', '00000000-0000-0000-0000-000000000001',
        (SELECT id FROM employees WHERE employee_number = 'E002' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM qms_positions WHERE code = 'top_management' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        NULL, 'organization:E002:top_management:GLOBAL', 'role', '最高管理者', '内部最高管理者；不替代 CMA 登记最高管理者', '2026-07-17', 'ORG-APPOINT-2026-01', '经人工确认的人员、岗位、场所和授权关系，仅用于 B6 隔离迁移演练。', 'corporate_evidence', 'active', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM employee_appointments WHERE appointment_key = 'organization:E002:top_management:GLOBAL' AND soft_delete = 0);

    INSERT INTO employee_appointments (
        id, company_id, employee_id, position_id, site_id, appointment_key,
        appointment_type, position_name, appointment_scope, appointed_at,
        source_document_number, source_excerpt, source_kind, status,
        publish, soft_delete, created, modified
    )
    SELECT '67f0bfce-f238-48b9-83fa-8b0a85748df6', '00000000-0000-0000-0000-000000000001',
        (SELECT id FROM employees WHERE employee_number = 'E002' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM qms_positions WHERE code = 'authorized_signatory' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        NULL, 'organization:E002:authorized_signatory:GLOBAL', 'authorization', '授权签字人', '两场所灵活轮调授权签字人', '2026-07-17', 'ORG-APPOINT-2026-01', '经人工确认的人员、岗位、场所和授权关系，仅用于 B6 隔离迁移演练。', 'corporate_evidence', 'active', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM employee_appointments WHERE appointment_key = 'organization:E002:authorized_signatory:GLOBAL' AND soft_delete = 0);

    INSERT INTO employee_appointments (
        id, company_id, employee_id, position_id, site_id, appointment_key,
        appointment_type, position_name, appointment_scope, appointed_at,
        source_document_number, source_excerpt, source_kind, status,
        publish, soft_delete, created, modified
    )
    SELECT 'a1b2945b-93a2-4dd0-8993-8a089b6092db', '00000000-0000-0000-0000-000000000001',
        (SELECT id FROM employees WHERE employee_number = 'E002' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM qms_positions WHERE code = 'internal_auditor' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        NULL, 'organization:E002:internal_auditor:GLOBAL', 'role', '内审员', '通常担任内审组长', '2026-07-17', 'ORG-APPOINT-2026-01', '经人工确认的人员、岗位、场所和授权关系，仅用于 B6 隔离迁移演练。', 'corporate_evidence', 'active', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM employee_appointments WHERE appointment_key = 'organization:E002:internal_auditor:GLOBAL' AND soft_delete = 0);

    INSERT INTO employee_appointments (
        id, company_id, employee_id, position_id, site_id, appointment_key,
        appointment_type, position_name, appointment_scope, appointed_at,
        source_document_number, source_excerpt, source_kind, status,
        publish, soft_delete, created, modified
    )
    SELECT '57a285a8-8a9d-4ea9-8bfe-2d691621ec69', '00000000-0000-0000-0000-000000000001',
        (SELECT id FROM employees WHERE employee_number = 'E002' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM qms_positions WHERE code = 'supervisor' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM sites WHERE code = 'PLACE01' AND soft_delete = 0 LIMIT 1), 'organization:E002:supervisor:PLACE01', 'role', '监督员', '乌鲁木齐实验室监督工作', '2026-07-17', 'ORG-APPOINT-2026-01', '经人工确认的人员、岗位、场所和授权关系，仅用于 B6 隔离迁移演练。', 'corporate_evidence', 'active', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM employee_appointments WHERE appointment_key = 'organization:E002:supervisor:PLACE01' AND soft_delete = 0);

    INSERT INTO employee_appointments (
        id, company_id, employee_id, position_id, site_id, appointment_key,
        appointment_type, position_name, appointment_scope, appointed_at,
        source_document_number, source_excerpt, source_kind, status,
        publish, soft_delete, created, modified
    )
    SELECT '0877674a-52d9-4ae1-8824-6360e71f913a', '00000000-0000-0000-0000-000000000001',
        (SELECT id FROM employees WHERE employee_number = 'E002' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM qms_positions WHERE code = 'system_administrator' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        NULL, 'organization:E002:system_administrator:GLOBAL', 'responsibility', 'LIMS系统管理员', 'LIMS 系统管理员', '2026-07-17', 'ORG-APPOINT-2026-01', '经人工确认的人员、岗位、场所和授权关系，仅用于 B6 隔离迁移演练。', 'corporate_evidence', 'active', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM employee_appointments WHERE appointment_key = 'organization:E002:system_administrator:GLOBAL' AND soft_delete = 0);

    INSERT INTO employee_appointments (
        id, company_id, employee_id, position_id, site_id, appointment_key,
        appointment_type, position_name, appointment_scope, appointed_at,
        source_document_number, source_excerpt, source_kind, status,
        publish, soft_delete, created, modified
    )
    SELECT '0e4dbb1f-daa8-4fbd-8846-44c8a3a230f3', '00000000-0000-0000-0000-000000000001',
        (SELECT id FROM employees WHERE employee_number = 'E005' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM qms_positions WHERE code = 'site_quality_coordinator' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        NULL, 'organization:E005:site_quality_coordinator:GLOBAL', 'role', '场所质量协调人', '质量负责人代理/两场所质量协调；代理启用需另行受控记录', '2026-07-17', 'ORG-APPOINT-2026-01', '经人工确认的人员、岗位、场所和授权关系，仅用于 B6 隔离迁移演练。', 'corporate_evidence', 'active', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM employee_appointments WHERE appointment_key = 'organization:E005:site_quality_coordinator:GLOBAL' AND soft_delete = 0);

    INSERT INTO employee_appointments (
        id, company_id, employee_id, position_id, site_id, appointment_key,
        appointment_type, position_name, appointment_scope, appointed_at,
        source_document_number, source_excerpt, source_kind, status,
        publish, soft_delete, created, modified
    )
    SELECT 'e5702735-ae57-4db4-8a66-fae02b3e0ae8', '00000000-0000-0000-0000-000000000001',
        (SELECT id FROM employees WHERE employee_number = 'E005' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM qms_positions WHERE code = 'technical_manager' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        NULL, 'organization:E005:technical_manager:GLOBAL', 'role', '技术负责人', '总体技术责任人', '2026-07-17', 'ORG-APPOINT-2026-01', '经人工确认的人员、岗位、场所和授权关系，仅用于 B6 隔离迁移演练。', 'corporate_evidence', 'active', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM employee_appointments WHERE appointment_key = 'organization:E005:technical_manager:GLOBAL' AND soft_delete = 0);

    INSERT INTO employee_appointments (
        id, company_id, employee_id, position_id, site_id, appointment_key,
        appointment_type, position_name, appointment_scope, appointed_at,
        source_document_number, source_excerpt, source_kind, status,
        publish, soft_delete, created, modified
    )
    SELECT 'aedf30ad-3633-4e3d-8910-c2b2858ad938', '00000000-0000-0000-0000-000000000001',
        (SELECT id FROM employees WHERE employee_number = 'E005' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM qms_positions WHERE code = 'authorized_signatory' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM sites WHERE code = 'PLACE01' AND soft_delete = 0 LIMIT 1), 'organization:E005:authorized_signatory:PLACE01', 'authorization', '授权签字人', '乌鲁木齐授权签字人', '2026-07-17', 'ORG-APPOINT-2026-01', '经人工确认的人员、岗位、场所和授权关系，仅用于 B6 隔离迁移演练。', 'corporate_evidence', 'active', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM employee_appointments WHERE appointment_key = 'organization:E005:authorized_signatory:PLACE01' AND soft_delete = 0);

    INSERT INTO employee_appointments (
        id, company_id, employee_id, position_id, site_id, appointment_key,
        appointment_type, position_name, appointment_scope, appointed_at,
        source_document_number, source_excerpt, source_kind, status,
        publish, soft_delete, created, modified
    )
    SELECT 'a7dff8ea-7bad-4db3-814d-00dfa6c06041', '00000000-0000-0000-0000-000000000001',
        (SELECT id FROM employees WHERE employee_number = 'E005' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM qms_positions WHERE code = 'authorized_signatory' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM sites WHERE code = 'PLACE02' AND soft_delete = 0 LIMIT 1), 'organization:E005:authorized_signatory:PLACE02', 'authorization', '授权签字人', '和田授权签字人', '2026-07-17', 'ORG-APPOINT-2026-01', '经人工确认的人员、岗位、场所和授权关系，仅用于 B6 隔离迁移演练。', 'corporate_evidence', 'active', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM employee_appointments WHERE appointment_key = 'organization:E005:authorized_signatory:PLACE02' AND soft_delete = 0);

    INSERT INTO employee_appointments (
        id, company_id, employee_id, position_id, site_id, appointment_key,
        appointment_type, position_name, appointment_scope, appointed_at,
        source_document_number, source_excerpt, source_kind, status,
        publish, soft_delete, created, modified
    )
    SELECT '541ac9ab-8023-4718-8025-5749f4eccd43', '00000000-0000-0000-0000-000000000001',
        (SELECT id FROM employees WHERE employee_number = 'E005' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM qms_positions WHERE code = 'internal_auditor' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        NULL, 'organization:E005:internal_auditor:GLOBAL', 'role', '内审员', '内审组员候选', '2026-07-17', 'ORG-APPOINT-2026-01', '经人工确认的人员、岗位、场所和授权关系，仅用于 B6 隔离迁移演练。', 'corporate_evidence', 'active', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM employee_appointments WHERE appointment_key = 'organization:E005:internal_auditor:GLOBAL' AND soft_delete = 0);

    INSERT INTO employee_appointments (
        id, company_id, employee_id, position_id, site_id, appointment_key,
        appointment_type, position_name, appointment_scope, appointed_at,
        source_document_number, source_excerpt, source_kind, status,
        publish, soft_delete, created, modified
    )
    SELECT 'f685db8b-a2a2-4139-8721-074dbfc429bf', '00000000-0000-0000-0000-000000000001',
        (SELECT id FROM employees WHERE employee_number = 'E003' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM qms_positions WHERE code = 'technical_manager' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM sites WHERE code = 'PLACE01' AND soft_delete = 0 LIMIT 1), 'organization:E003:technical_manager:PLACE01', 'role', '技术负责人', '乌鲁木齐技术负责人', '2026-07-17', 'ORG-APPOINT-2026-01', '经人工确认的人员、岗位、场所和授权关系，仅用于 B6 隔离迁移演练。', 'corporate_evidence', 'active', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM employee_appointments WHERE appointment_key = 'organization:E003:technical_manager:PLACE01' AND soft_delete = 0);

    INSERT INTO employee_appointments (
        id, company_id, employee_id, position_id, site_id, appointment_key,
        appointment_type, position_name, appointment_scope, appointed_at,
        source_document_number, source_excerpt, source_kind, status,
        publish, soft_delete, created, modified
    )
    SELECT '10bb6199-3d1e-4e53-8535-acbdba23d832', '00000000-0000-0000-0000-000000000001',
        (SELECT id FROM employees WHERE employee_number = 'E003' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM qms_positions WHERE code = 'authorized_signatory' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM sites WHERE code = 'PLACE01' AND soft_delete = 0 LIMIT 1), 'organization:E003:authorized_signatory:PLACE01', 'authorization', '授权签字人', '乌鲁木齐固定授权签字人', '2026-07-17', 'ORG-APPOINT-2026-01', '经人工确认的人员、岗位、场所和授权关系，仅用于 B6 隔离迁移演练。', 'corporate_evidence', 'active', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM employee_appointments WHERE appointment_key = 'organization:E003:authorized_signatory:PLACE01' AND soft_delete = 0);

    INSERT INTO employee_appointments (
        id, company_id, employee_id, position_id, site_id, appointment_key,
        appointment_type, position_name, appointment_scope, appointed_at,
        source_document_number, source_excerpt, source_kind, status,
        publish, soft_delete, created, modified
    )
    SELECT '772089a2-5f1d-46dc-8078-6def1fee5c0d', '00000000-0000-0000-0000-000000000001',
        (SELECT id FROM employees WHERE employee_number = 'E003' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM qms_positions WHERE code = 'internal_auditor' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        NULL, 'organization:E003:internal_auditor:GLOBAL', 'role', '内审员', '内审组员候选', '2026-07-17', 'ORG-APPOINT-2026-01', '经人工确认的人员、岗位、场所和授权关系，仅用于 B6 隔离迁移演练。', 'corporate_evidence', 'active', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM employee_appointments WHERE appointment_key = 'organization:E003:internal_auditor:GLOBAL' AND soft_delete = 0);

    INSERT INTO employee_appointments (
        id, company_id, employee_id, position_id, site_id, appointment_key,
        appointment_type, position_name, appointment_scope, appointed_at,
        source_document_number, source_excerpt, source_kind, status,
        publish, soft_delete, created, modified
    )
    SELECT '0804d76b-73f6-4ef8-8f95-7cfcde6b655a', '00000000-0000-0000-0000-000000000001',
        (SELECT id FROM employees WHERE employee_number = 'E004' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM qms_positions WHERE code = 'technical_manager' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM sites WHERE code = 'PLACE02' AND soft_delete = 0 LIMIT 1), 'organization:E004:technical_manager:PLACE02', 'role', '技术负责人', '和田技术负责人', '2026-07-17', 'ORG-APPOINT-2026-01', '经人工确认的人员、岗位、场所和授权关系，仅用于 B6 隔离迁移演练。', 'corporate_evidence', 'active', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM employee_appointments WHERE appointment_key = 'organization:E004:technical_manager:PLACE02' AND soft_delete = 0);

    INSERT INTO employee_appointments (
        id, company_id, employee_id, position_id, site_id, appointment_key,
        appointment_type, position_name, appointment_scope, appointed_at,
        source_document_number, source_excerpt, source_kind, status,
        publish, soft_delete, created, modified
    )
    SELECT '0b30c83e-3a2d-472c-8fa9-9f6315fef872', '00000000-0000-0000-0000-000000000001',
        (SELECT id FROM employees WHERE employee_number = 'E004' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM qms_positions WHERE code = 'authorized_signatory' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM sites WHERE code = 'PLACE02' AND soft_delete = 0 LIMIT 1), 'organization:E004:authorized_signatory:PLACE02', 'authorization', '授权签字人', '和田固定授权签字人', '2026-07-17', 'ORG-APPOINT-2026-01', '经人工确认的人员、岗位、场所和授权关系，仅用于 B6 隔离迁移演练。', 'corporate_evidence', 'active', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM employee_appointments WHERE appointment_key = 'organization:E004:authorized_signatory:PLACE02' AND soft_delete = 0);

    INSERT INTO employee_appointments (
        id, company_id, employee_id, position_id, site_id, appointment_key,
        appointment_type, position_name, appointment_scope, appointed_at,
        source_document_number, source_excerpt, source_kind, status,
        publish, soft_delete, created, modified
    )
    SELECT '4d503757-8720-4b3f-8cad-4e70ad24e292', '00000000-0000-0000-0000-000000000001',
        (SELECT id FROM employees WHERE employee_number = 'E006' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM qms_positions WHERE code = 'document_controller' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM sites WHERE code = 'PLACE01' AND soft_delete = 0 LIMIT 1), 'organization:E006:document_controller:PLACE01', 'role', '文件管理员', '乌鲁木齐文件管理员', '2026-07-17', 'ORG-APPOINT-2026-01', '经人工确认的人员、岗位、场所和授权关系，仅用于 B6 隔离迁移演练。', 'corporate_evidence', 'active', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM employee_appointments WHERE appointment_key = 'organization:E006:document_controller:PLACE01' AND soft_delete = 0);

    INSERT INTO employee_appointments (
        id, company_id, employee_id, position_id, site_id, appointment_key,
        appointment_type, position_name, appointment_scope, appointed_at,
        source_document_number, source_excerpt, source_kind, status,
        publish, soft_delete, created, modified
    )
    SELECT '5859cc4e-64b5-49dd-8f6b-7efb3dd6f922', '00000000-0000-0000-0000-000000000001',
        (SELECT id FROM employees WHERE employee_number = 'E010' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM qms_positions WHERE code = 'equipment_manager' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM sites WHERE code = 'PLACE01' AND soft_delete = 0 LIMIT 1), 'organization:E010:equipment_manager:PLACE01', 'role', '设备管理员', '乌鲁木齐设备管理员', '2026-07-17', 'ORG-APPOINT-2026-01', '经人工确认的人员、岗位、场所和授权关系，仅用于 B6 隔离迁移演练。', 'corporate_evidence', 'active', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM employee_appointments WHERE appointment_key = 'organization:E010:equipment_manager:PLACE01' AND soft_delete = 0);

    INSERT INTO employee_appointments (
        id, company_id, employee_id, position_id, site_id, appointment_key,
        appointment_type, position_name, appointment_scope, appointed_at,
        source_document_number, source_excerpt, source_kind, status,
        publish, soft_delete, created, modified
    )
    SELECT '3b9687ed-a4d9-4485-88cc-88597536a143', '00000000-0000-0000-0000-000000000001',
        (SELECT id FROM employees WHERE employee_number = 'E012' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM qms_positions WHERE code = 'document_controller' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM sites WHERE code = 'PLACE02' AND soft_delete = 0 LIMIT 1), 'organization:E012:document_controller:PLACE02', 'role', '文件管理员', '和田文件管理员', '2026-07-17', 'ORG-APPOINT-2026-01', '经人工确认的人员、岗位、场所和授权关系，仅用于 B6 隔离迁移演练。', 'corporate_evidence', 'active', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM employee_appointments WHERE appointment_key = 'organization:E012:document_controller:PLACE02' AND soft_delete = 0);

    INSERT INTO employee_appointments (
        id, company_id, employee_id, position_id, site_id, appointment_key,
        appointment_type, position_name, appointment_scope, appointed_at,
        source_document_number, source_excerpt, source_kind, status,
        publish, soft_delete, created, modified
    )
    SELECT '59215b9a-4fe6-41aa-8ad5-c65a515d6219', '00000000-0000-0000-0000-000000000001',
        (SELECT id FROM employees WHERE employee_number = 'E013' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM qms_positions WHERE code = 'equipment_manager' AND publish = 1 AND soft_delete = 0 LIMIT 1),
        (SELECT id FROM sites WHERE code = 'PLACE02' AND soft_delete = 0 LIMIT 1), 'organization:E013:equipment_manager:PLACE02', 'role', '设备管理员', '和田设备管理员', '2026-07-17', 'ORG-APPOINT-2026-01', '经人工确认的人员、岗位、场所和授权关系，仅用于 B6 隔离迁移演练。', 'corporate_evidence', 'active', 1, 0, NOW(), NOW()
    WHERE NOT EXISTS (SELECT 1 FROM employee_appointments WHERE appointment_key = 'organization:E013:equipment_manager:PLACE02' AND soft_delete = 0);

    UPDATE users SET employee_id = 'bf26c847-1181-4ed5-9881-563a6585475e', modified = NOW()
    WHERE username = 'admin' AND soft_delete = 0;

    IF (SELECT COUNT(*) FROM employee_appointments WHERE appointment_key IN ('organization:E000:company_general_manager:GLOBAL','organization:E000:top_management:GLOBAL','organization:E000:authorized_signatory:GLOBAL','organization:E000:internal_auditor:GLOBAL','organization:E000:supervisor:PLACE02','organization:E002:quality_manager:GLOBAL','organization:E002:top_management:GLOBAL','organization:E002:authorized_signatory:GLOBAL','organization:E002:internal_auditor:GLOBAL','organization:E002:supervisor:PLACE01','organization:E002:system_administrator:GLOBAL','organization:E005:site_quality_coordinator:GLOBAL','organization:E005:technical_manager:GLOBAL','organization:E005:authorized_signatory:PLACE01','organization:E005:authorized_signatory:PLACE02','organization:E005:internal_auditor:GLOBAL','organization:E003:technical_manager:PLACE01','organization:E003:authorized_signatory:PLACE01','organization:E003:internal_auditor:GLOBAL','organization:E004:technical_manager:PLACE02','organization:E004:authorized_signatory:PLACE02','organization:E006:document_controller:PLACE01','organization:E010:equipment_manager:PLACE01','organization:E012:document_controller:PLACE02','organization:E013:equipment_manager:PLACE02') AND soft_delete = 0) <> 25 THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'B6 appointment assertion failed';
    END IF;
    IF (
        SELECT COUNT(*) FROM employee_appointments ea
        JOIN employees e ON e.id = ea.employee_id
        JOIN qms_positions p ON p.id = ea.position_id
        WHERE e.name = '刘恒春' AND p.code = 'quality_manager'
          AND ea.status = 'active' AND ea.soft_delete = 0
    ) <> 0 THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'B6 least privilege assertion failed';
    END IF;
    COMMIT;
END//
CALL qms_b6_apply_organization()//
DROP PROCEDURE qms_b6_apply_organization//
DELIMITER ;