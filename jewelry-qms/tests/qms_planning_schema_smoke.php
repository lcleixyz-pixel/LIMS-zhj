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

function assert_not_contains(string $needle, string $haystack, string $message): void
{
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Unexpected: ' . $needle . PHP_EOL);
        exit(1);
    }
}

$newTables = [
    'qms_sources',
    'qms_clauses',
    'qms_clause_texts',
    'qms_elements',
    'qms_element_clause_links',
    'qms_element_documents',
    'qms_element_responsibilities',
    'qms_manual_sections',
    'qms_business_modules',
    'qms_business_module_elements',
    'qms_agent_suggestions',
    'qms_reference_procedure_matches',
    'qms_document_assets',
    'qms_structured_documents',
    'qms_document_blocks',
    'qms_document_block_links',
    'qms_document_change_logs',
    'qms_positions',
    'qms_quality_policies',
    'qms_quality_objectives',
];

foreach ($newTables as $table) {
    assert_contains('CREATE TABLE `' . $table . '`', $schema, 'Base schema includes ' . $table);
    assert_contains('CREATE TABLE IF NOT EXISTS `' . $table . '`', $migration, 'Idempotent migration includes ' . $table);
}

foreach ([
    'qms_requirement_elements',
    'qms_element_clause_mappings',
    'qms_responsibility_matrix',
    'qms_document_sections',
    'qms_trace_links',
    'qms_import_batches',
    'qms_import_candidates',
] as $legacyTable) {
    assert_not_contains('CREATE TABLE `' . $legacyTable . '`', $schema, 'Base schema removes legacy table ' . $legacyTable);
    assert_not_contains('CREATE TABLE IF NOT EXISTS `' . $legacyTable . '`', $migration, 'Migration removes legacy table ' . $legacyTable);
}

assert_contains('`key` varchar(80) NOT NULL', $schema, 'Elements use an invisible stable key');
assert_contains('UNIQUE KEY `element_key` (`key`)', $schema, 'Element key is unique');
assert_not_contains('`code` varchar(20) NOT NULL COMMENT \'要素编号', $schema, 'Elements do not carry business numbers');
assert_not_contains('`manual_section_number`', $schema, 'Manual section numbers are not stored on elements');
assert_not_contains('`manual_section_title`', $schema, 'Manual section titles are not stored on elements');
assert_contains('`clause_number` varchar(80) NOT NULL', $schema, 'Clause numbers live on qms_clauses');
assert_contains('`section_number` varchar(80) NOT NULL', $schema, 'Manual section numbers live on qms_manual_sections');
assert_contains('`is_primary` tinyint(1) DEFAULT 0', $schema, 'Element clause links can mark primary 27025 mapping');
assert_contains('`freshness_checked_at` date DEFAULT NULL', $schema, 'Sources store structured freshness date');
assert_contains('`freshness_result` varchar(300) DEFAULT NULL', $schema, 'Sources store structured freshness result');
assert_contains('`next_freshness_due` date DEFAULT NULL', $schema, 'Sources store next freshness due date');
assert_contains('`procedure_doc_id` varchar(36) DEFAULT NULL', $schema, 'Record forms can link to procedure documents');
assert_contains("enum('open','accepted','rejected')", $schema, 'Agent suggestions are advisory and reviewable');
assert_contains('`source_kind` enum(\'external_basis\',\'quality_manual\',\'procedure\',\'work_instruction\',\'record_form\',\'reference_file\')', $schema, 'Document assets classify archived source files');
assert_contains('UNIQUE KEY `asset_record_form_template` (`source_kind`,`record_form_template_id`)', $schema, 'Record form assets are unique per template, not per reused source path');
assert_contains('`markdown_path` varchar(500) DEFAULT NULL', $schema, 'Structured documents keep rendered markdown paths');
assert_contains('UNIQUE KEY `structured_document_source_asset` (`document_role`,`source_asset_id`)', $schema, 'Structured documents are unique per source asset');
assert_not_contains('UNIQUE KEY `structured_document` (`document_role`,`doc_number`,`version`)', $schema, 'Structured documents do not collapse same-number record form variants');
assert_contains('`block_type` enum(\'section\',\'purpose\',\'scope\',\'responsibility\',\'process_step\',\'control_requirement\',\'record_requirement\',\'form_schema\',\'clause_trace\',\'text\')', $schema, 'Document blocks are typed for modular tracing');
assert_contains('`record_form_template_id` varchar(36) DEFAULT NULL', $schema, 'Document block links can point to record form templates');
assert_contains('UNIQUE KEY `structured_block` (`structured_document_id`,`stable_key`)', $schema, 'Document blocks have stable keys inside each structured document');
assert_contains('`revision_note` text NOT NULL', $schema, 'Document change logs require revision notes');
assert_contains('`archive_path` varchar(500) DEFAULT NULL', $schema, 'Document change logs point to render archives');

echo "qms_planning_schema_smoke passed\n";
