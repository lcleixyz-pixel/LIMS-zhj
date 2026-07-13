<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\QmsManualProcedureAlignmentService;
use app\service\QmsManualProcedureTraceService;

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

function alignment_index_by_id(array $findings): array
{
    $indexed = [];
    foreach ($findings as $finding) {
        $indexed[(string)$finding['finding_id']] = $finding;
    }

    return $indexed;
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

$traceSnapshot = QmsManualProcedureTraceService::fromSnapshot($fixture . '/trace-snapshot.json');
alignment_assert($traceSnapshot['8.3'][0]['procedure_number'] === 'XZTC/CX-08-2022', 'Formal trace routes 8.3 to CX-08');

$result = QmsManualProcedureAlignmentService::check($loaded, $traceSnapshot);
$findings = alignment_index_by_id($result['findings']);

alignment_assert($findings['Y14']['status'] === 'conflict', 'Y14 detects allow/prohibit conflict');
alignment_assert($findings['Y14']['procedure_number'] === 'XZTC/CX-08-2022', 'Y14 routes to CX-08');
alignment_assert(str_contains((string)$findings['Y14']['evidence_excerpt'], '不允许进行手写改动'), 'Y14 keeps contrary evidence');

alignment_assert($findings['Y15']['status'] === 'conflict', 'Y15 detects 6-year versus 3-year conflict');
alignment_assert($findings['Y15']['expected']['years'] === 6, 'Y15 keeps expected years');
alignment_assert($findings['Y15']['observed']['years'] === 3, 'Y15 extracts observed years');

alignment_assert($findings['Y17']['status'] === 'conflict', 'Y17 detects internal version-rule conflict');
alignment_assert($findings['Y17']['observed']['body']['threshold'] === 5, 'Y17 parses body threshold');
alignment_assert($findings['Y17']['observed']['appendix']['threshold'] === 10, 'Y17 parses appendix threshold');

alignment_assert(isset($findings['Y18']), 'Y18 finding is produced');
alignment_assert($findings['Y18']['status'] === 'conflict', 'Y18 detects body/records mismatch');
alignment_assert(
    in_array('管理评审计划表', $findings['Y18']['observed']['missing_from_records'], true),
    'Y18 lists the management-review plan as missing'
);
alignment_assert(
    in_array('XZTC/BG-20-02', $findings['Y18']['observed']['unexpected_record_codes'], true),
    'Y18 lists the internal-audit record code as unexpected'
);

alignment_assert(isset($findings['Y13-CX20']), 'CX-20 responsibility finding is produced');
alignment_assert($findings['Y13-CX20']['status'] === 'conflict', 'CX-20 clear responsibility conflict is detected');
alignment_assert($findings['Y13-CX21']['status'] === 'review_required', 'CX-21 unconfirmed general-manager alias is not guessed');
alignment_assert($findings['Y13-CX32']['status'] === 'review_required', 'CX-32 unknown manager aliases require review');
alignment_assert(
    in_array('公司总经理', $findings['Y13-CX21']['observed']['unconfirmed_aliases'], true),
    'CX-21 reports the unconfirmed general-manager alias'
);
alignment_assert($findings['Y13-CX32']['trace_source'] === 'fallback_target', 'CX-32 reports fallback trace source');
alignment_assert(in_array('XZTC/CX-32-2022', $result['trace_gaps'], true), 'Trace gap is listed at report level');

$consistent = $loaded;
$consistent['procedures']['XZTC/CX-08-2022']['text'] = str_replace(
    '本公司的管理体系文件不允许进行手写改动。',
    '本公司的管理体系文件允许授权手写划改，但不允许擦涂。',
    (string)$consistent['procedures']['XZTC/CX-08-2022']['text']
);
$consistentResult = QmsManualProcedureAlignmentService::check($consistent, $traceSnapshot);
$consistentFindings = alignment_index_by_id($consistentResult['findings']);
alignment_assert($consistentFindings['Y14']['status'] === 'consistent', 'Y14 does not treat the erasure prohibition as a handwritten-change prohibition');

echo "qms_manual_procedure_alignment_rules_smoke deterministic rules passed\n";
