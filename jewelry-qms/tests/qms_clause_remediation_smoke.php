<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\QmsClauseRemediationService;

function remediation_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$sourceDir = trim((string)getenv('T1_SOURCE_DIR'));
remediation_assert($sourceDir !== '' && is_dir($sourceDir), 'T1_SOURCE_DIR must point to the three reviewed Markdown sources');

$rows = QmsClauseRemediationService::buildRows($sourceDir);
$bySource = [];
foreach ($rows as $row) {
    $bySource[(string)$row['source_code']][(string)$row['clause_number']] = $row;
    remediation_assert((string)$row['original_text'] !== '', 'Every remediation row keeps source text');
    remediation_assert(hash('sha256', (string)$row['original_text']) === (string)$row['text_hash'], 'Every remediation row keeps a valid text hash');
    remediation_assert(str_starts_with((string)$row['locator'], 'markdown:'), 'Every remediation row keeps a Markdown locator');
}

remediation_assert(count($rows) === 62, 'Approved remediation contains exactly 62 requirement atoms');
remediation_assert(count($bySource['GB/T 27025-2019'] ?? []) === 7, 'GB/T 27025 contains 7 missing requirement atoms');
remediation_assert(count($bySource['CNAS-CL01-G001:2024'] ?? []) === 37, 'G001 contains 37 requirement atoms');
remediation_assert(count($bySource['CNAS-CL01-A015:2018'] ?? []) === 18, 'A015 contains 18 requirement atoms');

foreach (['3.1', '3.2', '3.3', '3.4', '3.5', '3.6', '3.7', '3.8', '3.9'] as $definition) {
    remediation_assert(!isset($bySource['GB/T 27025-2019'][$definition]), 'GB definition is excluded: ' . $definition);
}
foreach (['1', '2', '2.1', '2.2', '2.3', '2.4', '2.5', '2.6', '2.7', '2.8', '3', '4', '5', '6', '7', '8'] as $nonRequirement) {
    remediation_assert(!isset($bySource['CNAS-CL01-A015:2018'][$nonRequirement]), 'A015 heading/reference is excluded: ' . $nonRequirement);
}

remediation_assert(isset($bySource['CNAS-CL01-G001:2024']['6.6.1c)']), 'G001 inline clause 6.6.1c) is extracted');
remediation_assert(str_contains((string)$bySource['CNAS-CL01-G001:2024']['6.6.1c)']['original_text'], '能力验证'), 'G001 inline clause keeps its own text');
remediation_assert(isset($bySource['CNAS-CL01-A015:2018']['7.4.4']), 'A015 inline clause 7.4.4 is extracted');
remediation_assert(str_contains((string)$bySource['CNAS-CL01-A015:2018']['7.4.4']['original_text'], '安保措施'), 'A015 inline clause keeps its own text');

echo "qms_clause_remediation_smoke passed\n";
