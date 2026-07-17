-- G-R13-B6 迁移后只读核对。
SET NAMES utf8mb4;
SELECT 'appointments' item, COUNT(*) count
FROM employee_appointments
WHERE source_document_number = 'LIMS-TRIAL-ORG-20260717-01' AND status = 'active' AND soft_delete = 0
UNION ALL
SELECT 'active_sites', COUNT(*) FROM sites WHERE publish = 1 AND soft_delete = 0
UNION ALL
SELECT 'liu_quality_manager', COUNT(*)
FROM employee_appointments ea
JOIN employees e ON e.id = ea.employee_id
JOIN qms_positions p ON p.id = ea.position_id
WHERE e.name = '刘恒春' AND p.code = 'quality_manager'
  AND ea.status = 'active' AND ea.soft_delete = 0;