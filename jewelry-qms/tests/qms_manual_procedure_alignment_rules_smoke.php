<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\QmsManualProcedureAlignmentService;

function alignment_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function alignment_expect_exception(callable $callback, string $messagePart): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        alignment_assert(
            str_contains($exception->getMessage(), $messagePart),
            'Unexpected exception: ' . $exception->getMessage()
        );
        return;
    }

    alignment_assert(false, 'Expected exception containing: ' . $messagePart);
}

$fixture = __DIR__ . '/fixtures/qms_manual_procedure_alignment';
$loaded = QmsManualProcedureAlignmentService::loadInputs(
    $fixture . '/pilot-spec.json',
    $fixture . '/procedures'
);

alignment_assert($loaded['pilot_id'] === 'manual-procedure-alignment-fixture-v0.1', 'Pilot id is retained');
alignment_assert(count($loaded['procedures']) === 5, 'Exactly five pilot procedures are loaded');
alignment_assert(strlen((string)$loaded['manual']['sha256']) === 64, 'Manual SHA-256 is recorded');
alignment_assert((int)$loaded['manual']['lines']['8.3.3.2']['start'] > 0, 'Manual section line is located');
alignment_assert($loaded['procedures']['XZTC/CX-08-2022']['frontmatter']['manual_edit'] === false, 'Generated-layer flag is retained');

alignment_expect_exception(
    fn () => QmsManualProcedureAlignmentService::loadInputs(
        $fixture . '/invalid-spec.json',
        $fixture . '/procedures'
    ),
    '缺少 schema_version'
);

echo "qms_manual_procedure_alignment_rules_smoke input contract passed\n";
