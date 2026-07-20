<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$schema = (string)file_get_contents($root . '/database/jewelry_qms.sql');
$migration = (string)file_get_contents($root . '/database/migrations/20260714_qms_activity_responsibility_chain.sql');

function schema_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$tables = [
    'qms_responsibility_chain_versions',
    'qms_responsibility_activities',
    'qms_activity_responsibilities',
    'qms_responsibility_assignments',
    'qms_responsibility_approvals',
    'qms_position_aliases',
];
foreach ($tables as $table) {
    schema_assert(str_contains($schema, 'CREATE TABLE `' . $table . '`'), 'Base schema contains ' . $table);
    schema_assert(str_contains($migration, 'CREATE TABLE IF NOT EXISTS `' . $table . '`'), 'Migration contains ' . $table);
}
foreach (['source_kind', 'source_chain_version_id', 'source_responsibility_id', 'source_approval_id'] as $column) {
    schema_assert(str_contains($schema, '`' . $column . '`'), 'Appointment schema contains ' . $column);
    schema_assert(str_contains($migration, '`' . $column . '`'), 'Appointment migration contains ' . $column);
}

echo "qms_responsibility_schema_smoke passed\n";
