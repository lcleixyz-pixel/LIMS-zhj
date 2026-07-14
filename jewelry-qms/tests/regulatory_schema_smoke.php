<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use think\facade\Db;

function schema_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function table_exists(string $table): bool
{
    $rows = Db::query(
        'SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        [$table]
    );

    return (int)$rows[0]['total'] === 1;
}

function column_type(string $table, string $column): string
{
    $rows = Db::query(
        'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$table, $column]
    );

    return (string)($rows[0]['COLUMN_TYPE'] ?? '');
}

function data_type(string $table, string $column): string
{
    $rows = Db::query(
        'SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$table, $column]
    );

    return (string)($rows[0]['DATA_TYPE'] ?? '');
}

function run_migration(string $migration): void
{
    $statements = preg_split('/;\s*(?:\r?\n|$)/', $migration) ?: [];
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            Db::execute($statement);
        }
    }
}

$runTable = 'qms_regulatory_monitor_runs';
$candidateTable = 'qms_external_change_candidates';

schema_assert(table_exists($runTable), 'Missing table: ' . $runTable);
schema_assert(table_exists($candidateTable), 'Missing table: ' . $candidateTable);

$uniqueColumns = Db::query(
    "SELECT COLUMN_NAME
     FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = ?
       AND INDEX_NAME = 'company_source_item_content'
       AND NON_UNIQUE = 0
     ORDER BY SEQ_IN_INDEX",
    [$candidateTable]
);
schema_assert(
    array_column($uniqueColumns, 'COLUMN_NAME') === ['company_id', 'source_key', 'source_item_key', 'content_hash'],
    'Candidate unique key must cover company_id, source_key, source_item_key, content_hash in order'
);

foreach (['supersedes_candidate_id', 'review_status', 'promoted_event_id'] as $column) {
    schema_assert(column_type($candidateTable, $column) !== '', 'Missing candidate column: ' . $column);
}

foreach (['trigger_mode', 'started_at', 'finished_at', 'source_stats', 'candidate_stats', 'result_json', 'error_summary', 'rule_version'] as $column) {
    schema_assert(column_type($runTable, $column) !== '', 'Missing run column: ' . $column);
}

foreach (['source_key', 'source_item_key', 'source_url', 'normalized_url', 'content_hash', 'evidence_summary', 'evidence_refs', 'impact_analysis', 'reviewed_by', 'reviewed_at', 'review_comment', 'promoted_at'] as $column) {
    schema_assert(column_type($candidateTable, $column) !== '', 'Missing candidate column: ' . $column);
}

foreach ([
    [$runTable, 'source_stats'],
    [$runTable, 'candidate_stats'],
    [$runTable, 'result_json'],
    [$candidateTable, 'evidence_refs'],
    [$candidateTable, 'impact_analysis'],
] as [$table, $column]) {
    schema_assert(data_type($table, $column) === 'json', $table . '.' . $column . ' must be JSON');
}

schema_assert(
    column_type($candidateTable, 'company_id') === column_type('companies', 'id'),
    'Candidate company_id type must match companies.id'
);
schema_assert(
    column_type($candidateTable, 'monitor_run_id') === column_type($runTable, 'id'),
    'Candidate monitor_run_id type must match run id'
);
schema_assert(
    column_type($candidateTable, 'supersedes_candidate_id') === column_type($candidateTable, 'id'),
    'Candidate supersedes_candidate_id type must match candidate id'
);
schema_assert(
    column_type($candidateTable, 'promoted_event_id') === column_type('qms_external_change_events', 'id'),
    'Candidate promoted_event_id type must match external change event id'
);

schema_assert(
    column_type($runTable, 'status') === "enum('running','completed','partial_failed','failed')",
    'Run status contract is not calibrated'
);
schema_assert(
    column_type($candidateTable, 'review_status') === "enum('pending','confirmed_applicable','confirmed_not_applicable','deferred','promoted')",
    'Candidate review_status contract is not calibrated'
);

$migrationPath = dirname(__DIR__) . '/database/migrations/20260714_regulatory_monitor.sql';
$migration = (string)file_get_contents($migrationPath);
schema_assert(
    str_contains($migration, 'CREATE TABLE IF NOT EXISTS `' . $runTable . '`')
        && str_contains($migration, 'CREATE TABLE IF NOT EXISTS `' . $candidateTable . '`'),
    'Migration must create both tables idempotently'
);

run_migration($migration);
run_migration($migration);

$runModel = (string)file_get_contents(dirname(__DIR__) . '/app/model/QmsRegulatoryMonitorRun.php');
$candidateModel = (string)file_get_contents(dirname(__DIR__) . '/app/model/QmsExternalChangeCandidate.php');
foreach (['source_stats', 'candidate_stats', 'result_json'] as $field) {
    schema_assert(str_contains($runModel, "'" . $field . "' => 'json'"), 'Run model converts ' . $field . ' as JSON');
}
foreach (['evidence_refs', 'impact_analysis'] as $field) {
    schema_assert(str_contains($candidateModel, "'" . $field . "' => 'json'"), 'Candidate model converts ' . $field . ' as JSON');
}
foreach ([[$runModel, $runTable], [$candidateModel, $candidateTable]] as [$model, $table]) {
    schema_assert(str_contains($model, "protected \$name = '" . $table . "'"), 'Model maps table ' . $table);
    schema_assert(str_contains($model, "protected \$autoWriteTimestamp = 'datetime'"), 'Model declares datetime timestamps');
    schema_assert(str_contains($model, "protected \$createTime = 'created'"), 'Model declares created timestamp');
    schema_assert(str_contains($model, "protected \$updateTime = 'modified'"), 'Model declares modified timestamp');
}

echo "regulatory_schema_smoke passed\n";
