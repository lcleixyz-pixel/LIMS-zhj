-- 文件签批重提轮次。旧数据默认归入第 1 轮。
SET @schema_name = DATABASE();

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = @schema_name AND table_name = 'approvals' AND column_name = 'workflow_round') = 0,
  'ALTER TABLE `approvals` ADD COLUMN `workflow_round` int NOT NULL DEFAULT 1 AFTER `approval_level`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.statistics
   WHERE table_schema = @schema_name AND table_name = 'approvals' AND index_name = 'record_workflow_round') = 0,
  'ALTER TABLE `approvals` ADD KEY `record_workflow_round` (`model_name`,`record`,`workflow_round`,`record_status`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

