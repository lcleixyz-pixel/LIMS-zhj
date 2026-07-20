INSERT INTO record_form_templates (
    id, company_id, doc_number, canonical_doc_number, name, module,
    applicable_sites, responsible_position_code, print_template_key,
    field_schema, version, status, trial_batch, trial_note, review_status,
    review_note, reviewed_at, publish, soft_delete, created, modified
) VALUES (
    'SIM-BLIND-TPL-RPT-001',
    '00000000-0000-0000-0000-000000000001',
    'SIM-BLIND-RPT-01',
    'SIM-BLIND-RPT-01',
    'SIM-检测报告记录',
    '结果报告管理',
    '乌鲁木齐,和田',
    'authorized_signatory',
    'sim_blind_report',
    '[{"key":"report_id","label":"报告编号","type":"text","required":true},{"key":"sample_id","label":"样品编号","type":"text","required":true},{"key":"site","label":"检测场所","type":"text","required":true},{"key":"method_standard","label":"检测方法","type":"text","required":true},{"key":"cma_mark","label":"CMA标志","type":"boolean","required":true},{"key":"cnas_mark","label":"CNAS标志","type":"boolean","required":true},{"key":"authorized_signatory","label":"授权签字人","type":"person","required":true}]',
    'SIM/1',
    'trial_ready',
    'SIM-GOV-R2-20260719-BLIND',
    '仅供8014一次性盲测的冻结记录载体',
    'field_confirmed',
    'SIM盲测载体，不得正式发布',
    '2026-07-19 08:00:00',
    1,
    0,
    '2026-07-19 08:00:00',
    '2026-07-19 08:00:00'
);

INSERT INTO record_form_templates (
    id, company_id, doc_number, canonical_doc_number, name, module,
    applicable_sites, responsible_position_code, print_template_key,
    field_schema, version, status, trial_batch, trial_note, review_status,
    review_note, reviewed_at, publish, soft_delete, created, modified
) VALUES (
    'SIM-BLIND-TPL-RAW-001',
    '00000000-0000-0000-0000-000000000001',
    'SIM-BLIND-RAW-01',
    'SIM-BLIND-RAW-01',
    'SIM-检测原始记录',
    '技术记录',
    '乌鲁木齐,和田',
    'testing_staff',
    'sim_blind_raw',
    '[{"key":"conclusion","label":"检测结论","type":"textarea","required":true}]',
    'SIM/1',
    'trial_ready',
    'SIM-GOV-R2-20260719-BLIND',
    '仅供8014一次性盲测的冻结记录载体',
    'field_confirmed',
    'SIM盲测载体，不得正式发布',
    '2026-07-19 08:00:00',
    1,
    0,
    '2026-07-19 08:00:00',
    '2026-07-19 08:00:00'
);

INSERT INTO employees (
    id, company_id, primary_site_id, employee_number, name, entry_date,
    publish, soft_delete, created, modified
) VALUES (
    'SIM-BLIND-EMP-SIGNER-001',
    '00000000-0000-0000-0000-000000000001',
    '00000000-0000-0000-0000-000000000070',
    'SIM-BLIND-E-SIGNER-001',
    'SIM-盲测签发角色',
    '2025-01-01',
    1,
    0,
    '2026-07-19 08:00:00',
    '2026-07-19 08:00:00'
);

INSERT INTO users (
    id, company_id, employee_id, username, password, name, role,
    is_mr, is_approver, user_access, publish, soft_delete, created, modified
) VALUES (
    'SIM-BLIND-USER-SIGNER-001',
    '00000000-0000-0000-0000-000000000001',
    'SIM-BLIND-EMP-SIGNER-001',
    'SIM-blind-signer-001',
    '$2y$10$5J7Z7C5w7Hn2Vp9J4QbYnuQX2wLr2sF0u6Q5P1g8j3D4y7K9m1T2u',
    'SIM-盲测签发角色',
    'staff',
    0,
    1,
    '{"reports":["read","approve","issue"]}',
    1,
    0,
    '2026-07-19 08:00:00',
    '2026-07-19 08:00:00'
);

INSERT INTO employee_appointments (
    id, company_id, employee_id, position_id, site_id, appointment_key,
    appointment_type, position_name, appointment_scope, appointed_at,
    valid_until, source_document_number, source_excerpt, source_kind,
    status, publish, soft_delete, created, modified, created_by, modified_by
) VALUES (
    'SIM-BLIND-APPT-SIGNER-001',
    '00000000-0000-0000-0000-000000000001',
    'SIM-BLIND-EMP-SIGNER-001',
    '5b807a9b-6894-4ef5-809d-644dffcd42c7',
    '00000000-0000-0000-0000-000000000070',
    'SIM-BLIND-AUTH-SIGNER-001',
    'authorization',
    '授权签字人',
    '{"site":"乌鲁木齐","methods":["GB/T 16552-2017"],"actions":["approve","issue"]}',
    '2025-01-01',
    '2025-12-31',
    'SIM-AUTH-2025-001',
    'SIM授权范围限于乌鲁木齐场所和GB/T 16552-2017',
    'corporate_evidence',
    'active',
    1,
    0,
    '2026-07-19 08:00:00',
    '2026-07-19 08:00:00',
    '00000000-0000-0000-0000-000000000040',
    '00000000-0000-0000-0000-000000000040'
);

INSERT INTO record_form_instances (
    id, company_id, template_id, template_name, template_module,
    template_version, template_print_template_key, template_field_schema,
    doc_number, record_title, field_values, status, is_simulation,
    trial_batch, created, modified, created_by, modified_by
) VALUES (
    'SIM-BLIND-RPT-DB65-001',
    '00000000-0000-0000-0000-000000000001',
    'SIM-BLIND-TPL-RPT-001',
    'SIM-检测报告记录',
    '结果报告管理',
    'SIM/1',
    'sim_blind_report',
    '[{"key":"report_id","required":true},{"key":"sample_id","required":true},{"key":"site","required":true},{"key":"method_standard","required":true},{"key":"cma_mark","required":true},{"key":"cnas_mark","required":true},{"key":"authorized_signatory","required":true}]',
    'SIM-BLIND-RPT-DB65-001',
    'SIM-和田场所检测报告',
    '{"report_id":"SIM-BLIND-RPT-DB65-001","contract_id":"SIM-BLIND-CTR-DB65-001","sample_id":"SIM-BLIND-SMP-DB65-001","site":"和田","method_standard":"DB65/T 4828-2024","one_list_status":"out_of_list","cma_mark":true,"cma_statement":"检验检测机构资质认定范围内","cnas_state":"initial_application_not_submitted","cnas_mark":false,"authorized_signatory":"SIM-BLIND-EMP-SIGNER-001","report_status":"issued"}',
    'locked',
    1,
    'SIM-GOV-R2-20260719-BLIND',
    '2026-07-19 08:30:00',
    '2026-07-19 08:35:00',
    'SIM-BLIND-USER-SIGNER-001',
    'SIM-BLIND-USER-SIGNER-001'
);

INSERT INTO record_form_instances (
    id, company_id, template_id, template_name, template_module,
    template_version, template_print_template_key, template_field_schema,
    doc_number, record_title, field_values, status, is_simulation,
    trial_batch, created, modified, created_by, modified_by
) VALUES (
    'SIM-BLIND-RPT-CNAS-001',
    '00000000-0000-0000-0000-000000000001',
    'SIM-BLIND-TPL-RPT-001',
    'SIM-检测报告记录',
    '结果报告管理',
    'SIM/1',
    'sim_blind_report',
    '[{"key":"report_id","required":true},{"key":"sample_id","required":true},{"key":"site","required":true},{"key":"method_standard","required":true},{"key":"cma_mark","required":true},{"key":"cnas_mark","required":true},{"key":"authorized_signatory","required":true}]',
    'SIM-BLIND-RPT-CNAS-001',
    'SIM-乌鲁木齐场所检测报告',
    '{"report_id":"SIM-BLIND-RPT-CNAS-001","contract_id":"SIM-BLIND-CTR-CNAS-001","sample_id":"SIM-BLIND-SMP-CNAS-001","site":"乌鲁木齐","method_standard":"GB/T 16553-2017","one_list_status":"in_list_user_confirmed_for_sim","cma_mark":true,"cnas_state":"initial_application_not_submitted","cnas_mark":true,"cnas_statement":"CNAS认可范围内","authorized_signatory":"SIM-BLIND-EMP-SIGNER-001","report_status":"issued"}',
    'locked',
    1,
    'SIM-GOV-R2-20260719-BLIND',
    '2026-07-19 08:40:00',
    '2026-07-19 08:45:00',
    'SIM-BLIND-USER-SIGNER-001',
    'SIM-BLIND-USER-SIGNER-001'
);

INSERT INTO record_form_instances (
    id, company_id, template_id, template_name, template_module,
    template_version, template_print_template_key, template_field_schema,
    doc_number, record_title, field_values, status, is_simulation,
    trial_batch, created, modified, created_by, modified_by
) VALUES (
    'SIM-BLIND-RPT-AUTH-001',
    '00000000-0000-0000-0000-000000000001',
    'SIM-BLIND-TPL-RPT-001',
    'SIM-检测报告记录',
    '结果报告管理',
    'SIM/1',
    'sim_blind_report',
    '[{"key":"report_id","required":true},{"key":"sample_id","required":true},{"key":"site","required":true},{"key":"method_standard","required":true},{"key":"cma_mark","required":true},{"key":"cnas_mark","required":true},{"key":"authorized_signatory","required":true}]',
    'SIM-BLIND-RPT-AUTH-001',
    'SIM-授权范围验证报告',
    '{"report_id":"SIM-BLIND-RPT-AUTH-001","contract_id":"SIM-BLIND-CTR-AUTH-001","sample_id":"SIM-BLIND-SMP-AUTH-001","site":"和田","method_standard":"GB/T 38821-2020","one_list_status":"in_list_user_confirmed_for_sim","cma_mark":true,"cnas_state":"initial_application_not_submitted","cnas_mark":false,"authorized_signatory":"SIM-BLIND-EMP-SIGNER-001","report_status":"issued"}',
    'locked',
    1,
    'SIM-GOV-R2-20260719-BLIND',
    '2026-07-19 08:50:00',
    '2026-07-19 08:55:00',
    'SIM-BLIND-USER-SIGNER-001',
    'SIM-BLIND-USER-SIGNER-001'
);

INSERT INTO approvals (
    id, company_id, model_name, controller_name, record, user_id,
    approval_level, status, comments, approved_on, record_status,
    publish, soft_delete, created, modified, created_by
) VALUES (
    'SIM-BLIND-APPROVAL-AUTH-001',
    '00000000-0000-0000-0000-000000000001',
    'RecordFormInstance',
    'RecordFormInstances',
    'SIM-BLIND-RPT-AUTH-001',
    'SIM-BLIND-USER-SIGNER-001',
    3,
    'approved',
    'SIM报告批准并签发',
    '2026-07-19 08:55:00',
    1,
    1,
    0,
    '2026-07-19 08:55:00',
    '2026-07-19 08:55:00',
    'SIM-BLIND-USER-SIGNER-001'
);

INSERT INTO record_form_instances (
    id, company_id, template_id, template_name, template_module,
    template_version, template_print_template_key, template_field_schema,
    doc_number, record_title, field_values, status, is_simulation,
    trial_batch, created, modified, created_by, modified_by
) VALUES (
    'SIM-BLIND-RAW-SEM-001',
    '00000000-0000-0000-0000-000000000001',
    'SIM-BLIND-TPL-RAW-001',
    'SIM-检测原始记录',
    '技术记录',
    'SIM/1',
    'sim_blind_raw',
    '[{"key":"conclusion","label":"检测结论","type":"textarea","required":true}]',
    'SIM-BLIND-RAW-SEM-001',
    'SIM-样品检测原始记录',
    '{"conclusion":"符合委托要求"}',
    'locked',
    1,
    'SIM-GOV-R2-20260719-BLIND',
    '2026-07-19 09:00:00',
    '2026-07-19 09:05:00',
    'SIM-BLIND-USER-SIGNER-001',
    'SIM-BLIND-USER-SIGNER-001'
);

INSERT INTO histories (
    id, model_name, controller_name, action, record_id, user_id, details, created
) VALUES
(
    'SIM-BLIND-HISTORY-DB65-001',
    'RecordFormInstance',
    'RecordFormInstances',
    'issue',
    'SIM-BLIND-RPT-DB65-001',
    'SIM-BLIND-USER-SIGNER-001',
    '{"status":"locked","event":"report_issued"}',
    '2026-07-19 08:35:00'
),
(
    'SIM-BLIND-HISTORY-CNAS-001',
    'RecordFormInstance',
    'RecordFormInstances',
    'issue',
    'SIM-BLIND-RPT-CNAS-001',
    'SIM-BLIND-USER-SIGNER-001',
    '{"status":"locked","event":"report_issued"}',
    '2026-07-19 08:45:00'
),
(
    'SIM-BLIND-HISTORY-AUTH-001',
    'RecordFormInstance',
    'RecordFormInstances',
    'approve',
    'SIM-BLIND-RPT-AUTH-001',
    'SIM-BLIND-USER-SIGNER-001',
    '{"status":"approved","event":"report_authorized"}',
    '2026-07-19 08:55:00'
),
(
    'SIM-BLIND-HISTORY-RAW-001',
    'RecordFormInstance',
    'RecordFormInstances',
    'lock',
    'SIM-BLIND-RAW-SEM-001',
    'SIM-BLIND-USER-SIGNER-001',
    '{"status":"locked","event":"technical_record_locked"}',
    '2026-07-19 09:05:00'
);
