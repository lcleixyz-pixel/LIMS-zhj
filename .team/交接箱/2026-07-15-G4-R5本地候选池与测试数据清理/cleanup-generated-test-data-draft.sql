-- G4-R5 generated acceptance/smoke test data cleanup draft.
-- DRAFT ONLY. Do not run before user approval and fresh database backup.
-- Scope: rows with explicit test identifiers only.
SET NAMES utf8mb4;

START TRANSACTION;

-- B组验收测试设备及其测试检查结果/维护记录。
DELETE FROM compliance_check_results
WHERE JSON_SEARCH(fail_items, 'one', 'EQ-B-001') IS NOT NULL
   OR JSON_SEARCH(fail_items, 'one', 'B组验收测试设备') IS NOT NULL;

DELETE FROM equipment_maintenances
WHERE equipment_id = '00000000-0000-0000-0000-00000000b001';

DELETE FROM equipments
WHERE id = '00000000-0000-0000-0000-00000000b001'
   OR equipment_number = 'EQ-B-001'
   OR name = 'B组验收测试设备';

-- 记录要求覆盖缺口程序 smoke 数据。
DELETE FROM qms_agent_suggestions
WHERE title LIKE '%QP-COVERAGE-SMOKE%'
   OR content LIKE '%QP-COVERAGE-SMOKE%'
   OR evidence LIKE '%QP-COVERAGE-SMOKE%'
   OR evidence LIKE '%smoke-prc-record-coverage%';

DELETE FROM qms_document_blocks
WHERE structured_document_id = 'smoke-prc-record-coverage-struct'
   OR document_id = 'smoke-prc-record-coverage-doc';

DELETE FROM qms_structured_documents
WHERE id = 'smoke-prc-record-coverage-struct'
   OR document_id = 'smoke-prc-record-coverage-doc'
   OR doc_number = 'QP-COVERAGE-SMOKE'
   OR title = '记录要求覆盖缺口程序';

DELETE FROM documents
WHERE id = 'smoke-prc-record-coverage-doc'
   OR doc_number = 'QP-COVERAGE-SMOKE'
   OR title = '记录要求覆盖缺口程序';

-- TEST-LINK-20260709-191600 生成记录实例。
DELETE FROM record_form_instances
WHERE doc_number LIKE 'TEST-LINK-20260709-191600-%'
   OR generated_pdf_path LIKE '%TEST-LINK-20260709-191600%'
   OR generated_pdf_name LIKE '%TEST-LINK-20260709-191600%'
   OR record_title LIKE 'TEST-LINK-20260709-191600-%';

-- 注意：不删除 qms_document_assets 中的“人员培训评价表”模板资产。
-- 它没有 TEST-LINK 标识，更像记录表格模板资产，不是本批污染实例。

COMMIT;
