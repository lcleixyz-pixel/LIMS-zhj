<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\RecordFormPrintService;

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function rfp_assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

$schema = [
    [
        'key' => 'standard_rows',
        'label' => '标准物质报废明细',
        'type' => 'repeatable_table',
        'columns' => [
            ['key' => 'standard_name', 'label' => '标准物质名称', 'type' => 'text'],
            ['key' => 'disposal_reason', 'label' => '报废原因', 'type' => 'text'],
        ],
    ],
    [
        'key' => 'review_note',
        'label' => '审核意见',
        'type' => 'textarea',
    ],
];

$template = [
    'doc_number' => 'SIM-XZTC/BG-35-03',
    'name' => '[治理试运行] 标准物质报废申请表',
    'version' => 'A/0',
    'module' => 'SIM-GOV02-XZTC/CX-03-02-2022',
    'field_schema' => json_encode($schema, JSON_UNESCAPED_UNICODE),
];

$values = [
    'standard_rows' => [
        [
            'standard_name' => '金标片 G05',
            'disposal_reason' => '超过有效期',
        ],
    ],
    'review_note' => "同意报废\n纳入台账",
];

$printKey = 'tmp_schema_snapshot_' . bin2hex(random_bytes(4));
$printPath = dirname(__DIR__) . '/app/record_form_print/' . $printKey . '.php';
$templateCode = <<<'PHP'
<?php
use app\service\RecordFormPrintService as P;
?>
<html><body>
<?php foreach (($template['field_schema'] ?? []) as $field): ?>
    <?php if (($field['type'] ?? '') === 'repeatable_table'): ?>
        <h2><?= P::cell($field, 'label') ?></h2>
        <?php foreach (P::rows($values, (string)$field['key']) as $row): ?>
            <?php foreach (($field['columns'] ?? []) as $column): ?>
                <span><?= P::cell($row, (string)$column['key']) ?></span>
            <?php endforeach; ?>
        <?php endforeach; ?>
    <?php else: ?>
        <h2><?= P::cell($field, 'label') ?></h2>
        <p><?= nl2br(P::value($values, (string)$field['key'])) ?></p>
    <?php endif; ?>
<?php endforeach; ?>
</body></html>
PHP;

if (file_put_contents($printPath, $templateCode, LOCK_EX) === false) {
    fwrite(STDERR, 'Failed to create temporary print template' . PHP_EOL);
    exit(1);
}

try {
    $html = RecordFormPrintService::render($printKey, $template, $values);
} finally {
    if (is_file($printPath)) {
        unlink($printPath);
    }
}

rfp_assert_contains('标准物质报废明细', $html, 'Renders decoded repeatable table label from snapshot JSON schema');
rfp_assert_contains('金标片 G05', $html, 'Renders repeatable table values when schema is stored as JSON');
rfp_assert_contains("同意报废<br />\n纳入台账", $html, 'Renders textarea values when schema is stored as JSON');

echo "qms_record_form_print_schema_snapshot_smoke passed\n";
