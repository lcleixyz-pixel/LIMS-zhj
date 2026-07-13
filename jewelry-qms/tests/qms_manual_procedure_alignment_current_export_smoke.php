<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\QmsManualProcedureAlignmentService;

function current_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$repoRoot = dirname(__DIR__, 2);
$spec = __DIR__ . '/../docs/qms_manual_procedure_alignment_pilot-v0.1.json';
$procedureDir = $repoRoot . '/knowledge/internal/procedures';
$loaded = QmsManualProcedureAlignmentService::loadInputs($spec, $procedureDir);

current_assert((string)$loaded['manual']['doc_number'] === 'XZTC/SC', 'Current pilot uses the XZTC/SC manual');
current_assert(count($loaded['procedures']) === 5, 'Current pilot loads five generated procedures');
foreach ($loaded['procedures'] as $procedure) {
    current_assert(($procedure['frontmatter']['manual_edit'] ?? null) === false, 'Generated layer remains read-only');
    current_assert(strlen((string)$procedure['sha256']) === 64, 'Current export hash is recorded');
    current_assert((string)$procedure['version'] !== '', 'Current export version is recorded or inferred');
    current_assert(is_file((string)$procedure['path']), 'Current export keeps a resolvable path');
}

echo "qms_manual_procedure_alignment_current_export_smoke passed\n";
