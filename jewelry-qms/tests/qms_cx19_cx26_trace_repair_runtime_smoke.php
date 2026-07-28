<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/app/common.php';

use app\service\QmsFileGovernanceWorkbenchService;
use think\facade\Db;

(new think\App())->initialize();

function cx19_cx26_trace_repair_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$expectations = [
    'CX-19' => [
        'manual_section' => '8.4',
        'external' => [
            'CNAS-CL01-G001:2024 8.4',
            'CNAS-CL01:2018 8.4',
            'GB/T 27025-2019 8.4',
            '市场监管总局公告2023年第21号 2.12.7',
        ],
        'records' => [
            'SIM-XZTC/BG-19-01',
            'SIM-XZTC/BG-19-02',
            'SIM-XZTC/BG-19-03',
            'SIM-XZTC/BG-19-04',
        ],
    ],
    'CX-26' => [
        'manual_section' => '7.11',
        'external' => [
            'CNAS-CL01-G001:2024 7.11',
            'CNAS-CL01:2018 7.11',
            'GB/T 27025-2019 7.11',
            '市场监管总局公告2023年第21号 2.12.8',
        ],
        'records' => [
            'SIM-XZTC/BG-26-01',
            'SIM-XZTC/BG-26-02',
        ],
    ],
];

foreach ($expectations as $procedureCode => $expected) {
    $structured = Db::name('qms_structured_documents')
        ->whereLike('doc_number', 'SIM-GOV02-XZTC/' . $procedureCode . '%')
        ->where('version', 'GOV-TRIAL/0.2')
        ->where('soft_delete', 0)
        ->find();
    cx19_cx26_trace_repair_assert(
        is_array($structured),
        '8021 缺少 ' . $procedureCode . ' GOV-TRIAL/0.2'
    );

    $viewModel = QmsFileGovernanceWorkbenchService::detail((string)$structured['id']);
    cx19_cx26_trace_repair_assert(
        (string)($viewModel['semantic_guard']['status'] ?? '')
            === 'suspected_mismatch'
            && (string)(
                $viewModel['semantic_guard']['issues'][0]['reason_code'] ?? ''
            ) === 'mixed_relation',
        $procedureCode . ' 的候选章节正确，但要素与手册关系仍需拆分'
    );
    cx19_cx26_trace_repair_assert(
        ($viewModel['chain']['missing'] ?? []) === ['手册主链'],
        $procedureCode . ' 的混装手册关系不得计入独立主链'
    );

    $manualNumbers = array_column(
        $viewModel['chain']['manual_sections'] ?? [],
        'section_number'
    );
    cx19_cx26_trace_repair_assert(
        in_array((string)$expected['manual_section'], $manualNumbers, true),
        $procedureCode . ' 应保留候选手册 ' . (string)$expected['manual_section']
    );

    $externalLabels = array_map(
        static fn(array $row): string =>
            (string)($row['source_code'] ?? '') . ' ' . (string)($row['clause_number'] ?? ''),
        $viewModel['chain']['confirmed_external_sources'] ?? []
    );
    foreach ((array)$expected['external'] as $expectedExternal) {
        cx19_cx26_trace_repair_assert(
            in_array((string)$expectedExternal, $externalLabels, true),
            $procedureCode . ' 缺少外部依据主链：' . (string)$expectedExternal
        );
    }

    $recordNumbers = array_column(
        $viewModel['chain']['confirmed_record_evidence'] ?? [],
        'doc_number'
    );
    sort($recordNumbers);
    $expectedRecordNumbers = (array)$expected['records'];
    sort($expectedRecordNumbers);
    cx19_cx26_trace_repair_assert(
        $recordNumbers === $expectedRecordNumbers,
        $procedureCode . ' 的运行证据模板应完整且不重复'
    );

    $blockIds = Db::name('qms_document_blocks')
        ->where('structured_document_id', (string)$structured['id'])
        ->where('soft_delete', 0)
        ->column('id');
    $activeLinks = Db::name('qms_document_block_links')
        ->whereIn('block_id', $blockIds)
        ->where('soft_delete', 0)
        ->select()
        ->toArray();

    $recordLinks = array_values(array_filter(
        $activeLinks,
        static fn(array $link): bool => (string)($link['relation_type'] ?? '') === 'requires_record'
    ));
    cx19_cx26_trace_repair_assert(
        count($recordLinks) === count($expectedRecordNumbers),
        $procedureCode . ' 的运行记录关系应收敛为每个模板一条'
    );
    foreach ($recordLinks as $recordLink) {
        cx19_cx26_trace_repair_assert(
            empty($recordLink['element_id'])
                && empty($recordLink['clause_id'])
                && empty($recordLink['manual_section_id'])
                && empty($recordLink['position_id']),
            $procedureCode . ' 的运行记录关系不得夹带要素、条款、手册或岗位'
        );
    }

    $responsibleLinks = array_values(array_filter(
        $activeLinks,
        static fn(array $link): bool =>
            (string)($link['relation_type'] ?? '') === 'responsible'
            && !empty($link['position_id'])
    ));
    cx19_cx26_trace_repair_assert(
        count($responsibleLinks) === 1,
        $procedureCode . ' 应有一条独立责任岗位关系'
    );
}

echo "qms_cx19_cx26_trace_repair_runtime_smoke passed\n";
