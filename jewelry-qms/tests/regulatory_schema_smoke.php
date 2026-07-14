<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\model\QmsExternalChangeCandidate;
use app\model\QmsRegulatoryMonitorRun;
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

function column_length(string $table, string $column): int
{
    $rows = Db::query(
        'SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$table, $column]
    );

    return (int)($rows[0]['CHARACTER_MAXIMUM_LENGTH'] ?? 0);
}

function column_collation(string $table, string $column): string
{
    $rows = Db::query(
        'SELECT COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$table, $column]
    );

    return (string)($rows[0]['COLLATION_NAME'] ?? '');
}

function model_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function normalize_json_value(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }

    foreach ($value as $key => $item) {
        $value[$key] = normalize_json_value($item);
    }
    if (!array_is_list($value)) {
        ksort($value);
    }

    return $value;
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

$migrationPath = dirname(__DIR__) . '/database/migrations/20260714_regulatory_monitor.sql';
$migration = (string)file_get_contents($migrationPath);
schema_assert(
    str_contains($migration, 'CREATE TABLE IF NOT EXISTS `' . $runTable . '`')
        && str_contains($migration, 'CREATE TABLE IF NOT EXISTS `' . $candidateTable . '`'),
    'Migration must create both tables idempotently'
);
Db::execute(
    'ALTER TABLE `qms_external_change_candidates` '
    . 'MODIFY COLUMN `source_item_key` varchar(255) CHARACTER SET utf8mb4 '
    . "COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT '来源侧稳定项目标识'"
);
run_migration($migration);
run_migration($migration);
schema_assert(
    column_collation($candidateTable, 'source_item_key') === 'utf8mb4_bin',
    'Candidate source_item_key must use binary utf8mb4 collation'
);

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

foreach (['source_key', 'source_item_key', 'source_url', 'normalized_url', 'content_hash', 'evidence_summary', 'evidence_refs', 'evidence_json', 'impact_analysis', 'reviewed_by', 'reviewed_at', 'review_comment', 'promoted_at'] as $column) {
    schema_assert(column_type($candidateTable, $column) !== '', 'Missing candidate column: ' . $column);
}

foreach ([
    [$runTable, 'source_stats'],
    [$runTable, 'candidate_stats'],
    [$runTable, 'result_json'],
    [$candidateTable, 'evidence_refs'],
    [$candidateTable, 'evidence_json'],
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
    column_length($candidateTable, 'title') === column_length('qms_external_change_events', 'source_name'),
    'Candidate title length must match external change event source_name'
);
schema_assert(
    column_length($candidateTable, 'source_url') === column_length('qms_external_change_events', 'source_url'),
    'Candidate source_url length must match external change event source_url'
);
schema_assert(
    column_length($candidateTable, 'announcement_number') === column_length('qms_external_change_events', 'announcement_number'),
    'Candidate announcement_number length must match external change event announcement_number'
);

schema_assert(
    column_type($runTable, 'status') === "enum('running','completed','partial_failed','failed')",
    'Run status contract is not calibrated'
);
schema_assert(
    column_type($candidateTable, 'review_status') === "enum('pending','confirmed_applicable','confirmed_not_applicable','deferred','promoted')",
    'Candidate review_status contract is not calibrated'
);

$runId = qms_uuid();
$candidateId = qms_uuid();
$runJson = [
    'source_stats' => ['planned' => 2, 'successful' => 1, 'failed' => 1],
    'candidate_stats' => ['new' => 1, 'duplicate' => 0, 'updated' => 0],
    'result_json' => ['offline' => true, 'notes' => ['schema-smoke']],
];
$candidateJson = [
    'evidence_refs' => [['kind' => 'official_page', 'locator' => 'notice-1']],
    'evidence_json' => [
        'original_title' => str_repeat('原', 350),
        'original_url' => 'https://example.invalid/' . str_repeat('x', 550),
    ],
    'impact_analysis' => [
        'cma_scope_mark' => ['status' => 'no_match'],
        'cnas_accreditation' => ['status' => 'possible'],
        'qms_documents' => ['status' => 'direct'],
        'personnel_authorization' => ['status' => 'no_match'],
        'methods_resources' => ['status' => 'possible'],
        'lims_rules' => ['status' => 'direct'],
    ],
];
$modelFailure = null;

try {
    $run = new QmsRegulatoryMonitorRun();
    model_assert($run->getName() === $runTable, 'Run model table mapping is incorrect');
    $run->save([
        'id' => $runId,
        'run_code' => 'REG-SCHEMA-' . $runId,
        'trigger_mode' => 'manual',
        'started_at' => date('Y-m-d H:i:s'),
        'status' => 'completed',
        'source_stats' => $runJson['source_stats'],
        'candidate_stats' => $runJson['candidate_stats'],
        'result_json' => $runJson['result_json'],
        'rule_version' => 'schema-smoke-v1',
    ]);

    $candidate = new QmsExternalChangeCandidate();
    model_assert($candidate->getName() === $candidateTable, 'Candidate model table mapping is incorrect');
    $candidate->save([
        'id' => $candidateId,
        'monitor_run_id' => $runId,
        'source_key' => 'schema-smoke',
        'source_mode' => 'manual_only',
        'source_item_key' => 'notice-' . $candidateId,
        'title' => '法规监测候选模型往返测试',
        'source_url' => 'https://example.invalid/notices/' . $candidateId,
        'first_seen_at' => date('Y-m-d H:i:s'),
        'last_seen_at' => date('Y-m-d H:i:s'),
        'content_hash' => hash('sha256', $candidateId),
        'evidence_refs' => $candidateJson['evidence_refs'],
        'evidence_json' => $candidateJson['evidence_json'],
        'impact_analysis' => $candidateJson['impact_analysis'],
    ]);

    $storedRun = QmsRegulatoryMonitorRun::find($runId);
    $storedCandidate = QmsExternalChangeCandidate::find($candidateId);
    model_assert($storedRun instanceof QmsRegulatoryMonitorRun, 'Run model record was not readable');
    model_assert($storedCandidate instanceof QmsExternalChangeCandidate, 'Candidate model record was not readable');
    foreach ($runJson as $field => $expected) {
        model_assert(
            normalize_json_value($storedRun->getAttr($field)) === normalize_json_value($expected),
            'Run model JSON roundtrip failed: ' . $field
        );
    }
    foreach ($candidateJson as $field => $expected) {
        model_assert(
            normalize_json_value($storedCandidate->getAttr($field)) === normalize_json_value($expected),
            'Candidate model JSON roundtrip failed: ' . $field
        );
    }
    foreach ([[$runTable, $runId], [$candidateTable, $candidateId]] as [$table, $id]) {
        $timestamps = Db::name($table)->where('id', $id)->field(['created', 'modified'])->find();
        model_assert(is_array($timestamps), $table . ' timestamps were not readable');
        model_assert((string)$timestamps['created'] !== '', $table . ' created timestamp was not written');
        model_assert((string)$timestamps['modified'] !== '', $table . ' modified timestamp was not written');
    }

    $storedCandidate->delete();
    $storedRun->delete();
    model_assert(Db::name($candidateTable)->where('id', $candidateId)->count() === 0, 'Candidate model delete failed');
    model_assert(Db::name($runTable)->where('id', $runId)->count() === 0, 'Run model delete failed');
} catch (Throwable $exception) {
    $modelFailure = $exception;
} finally {
    Db::name($candidateTable)->where('id', $candidateId)->delete();
    Db::name($runTable)->where('id', $runId)->delete();
}

if ($modelFailure instanceof Throwable) {
    fwrite(STDERR, $modelFailure->getMessage() . PHP_EOL);
    exit(1);
}

echo "regulatory_schema_smoke passed\n";
