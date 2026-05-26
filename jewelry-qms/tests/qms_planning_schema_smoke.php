<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$schema = (string)file_get_contents($root . '/database/jewelry_qms.sql');
$migration = (string)file_get_contents($root . '/database/migrations/20260523_qms_planning.sql');

function assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

$tables = [
    'qms_sources',
    'qms_clauses',
    'qms_clause_texts',
    'qms_requirement_elements',
    'qms_element_clause_mappings',
    'qms_positions',
    'qms_responsibility_matrix',
    'qms_document_sections',
    'qms_trace_links',
    'qms_quality_policies',
    'qms_quality_objectives',
    'qms_import_batches',
    'qms_import_candidates',
];

foreach ($tables as $table) {
    assert_contains('CREATE TABLE `' . $table . '`', $schema, 'Base schema includes ' . $table);
    assert_contains('CREATE TABLE IF NOT EXISTS `' . $table . '`', $migration, 'Idempotent migration includes ' . $table);
}

assert_contains("enum('candidate','draft','pending_review','published','rejected','obsolete')", $schema, 'Planning review status enum is present');
assert_contains('`original_text` mediumtext', $schema, 'Clause original text table stores verbatim text separately from clause summaries');
assert_contains('`text_hash` varchar(64)', $schema, 'Clause original text table can track extracted text changes');
assert_contains("enum('decision_owner','organizer','participant')", $schema, 'Responsibility type enum is present');
assert_contains("enum('clause','requirement_element','document_section','document','record_form_template','business_module','quality_objective','position')", $schema, 'Trace link target type enum is present');

echo "qms_planning_schema_smoke passed\n";
