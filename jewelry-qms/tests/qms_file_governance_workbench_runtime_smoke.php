<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\QmsFileGovernanceWorkbenchService;
use think\facade\Db;

(new think\App())->initialize();

function workbench_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$structured = Db::name('qms_structured_documents')
    ->where('doc_number', 'SIM-GOV02-XZTC/CX-03-02-2022')
    ->where('version', 'GOV-TRIAL/0.2')
    ->where('soft_delete', 0)
    ->find();
workbench_runtime_assert(is_array($structured), '8021 缺少 SIM-GOV02-XZTC/CX-03-02-2022 GOV-TRIAL/0.2');

$before = [
    'structures' => Db::name('qms_structured_documents')->where('soft_delete', 0)->count(),
    'links' => Db::name('qms_document_block_links')->where('soft_delete', 0)->count(),
    'documents' => Db::name('documents')->where('soft_delete', 0)->count(),
];
$viewModel = QmsFileGovernanceWorkbenchService::detail((string)$structured['id']);
$after = [
    'structures' => Db::name('qms_structured_documents')->where('soft_delete', 0)->count(),
    'links' => Db::name('qms_document_block_links')->where('soft_delete', 0)->count(),
    'documents' => Db::name('documents')->where('soft_delete', 0)->count(),
];

workbench_runtime_assert($before === $after, '工作台读取不得改变数据库计数');
workbench_runtime_assert($viewModel !== [], 'CX-03-02 工作台不得为空');
workbench_runtime_assert(
    array_filter(
        $viewModel['chain']['record_evidence'],
        static fn(array $row): bool =>
            str_ends_with((string)($row['doc_number'] ?? ''), 'XZTC/BG-35-03')
            && str_contains((string)($row['name'] ?? ''), '标准物质报废申请表')
    ) !== [],
    'BG-35-03 应保持标准物质报废申请表治理映射'
);
workbench_runtime_assert(
    ($viewModel['artifacts']['continuous_url'] ?? '') !== '',
    'CX-03-02 应提供连续正文入口'
);
workbench_runtime_assert(
    ($viewModel['document']['document_url'] ?? '') !== '',
    'CX-03-02 应回链文件详情和签批状态'
);
workbench_runtime_assert(
    ($viewModel['chain']['missing'] ?? []) === ['手册主链', '运行证据主链'],
    'CX-03-02 的混装手册关系和继承记录关系均不得误报闭环'
);
workbench_runtime_assert(
    (int)($viewModel['chain']['review_summary']['pending_review'] ?? 0) === 1,
    'CX-03-02 应明确显示一条继承关系等待复核'
);
workbench_runtime_assert(
    (int)($viewModel['chain']['review_summary']['suspected_mismatch'] ?? 0) === 2
        && (string)($viewModel['semantic_guard']['issues'][0]['reason_code'] ?? '')
            === 'mixed_relation',
    'CX-03-02 应识别两条要素与手册章节混装关系'
);
$externalLabels = array_map(
    static fn(array $row): string => (string)($row['source_code'] ?? '') . ' ' . (string)($row['clause_number'] ?? ''),
    $viewModel['chain']['external_sources'] ?? []
);
foreach ([
    '市场监管总局公告2023年第21号 2.11.3',
    'CNAS-CL01-G001:2024 6.4.1a)',
    'CNAS-CL01:2018 6.5.2',
] as $expectedExternal) {
    workbench_runtime_assert(
        in_array($expectedExternal, $externalLabels, true),
        'CX-03-02 缺少外部依据：' . $expectedExternal
    );
}
$manualNumbers = array_column($viewModel['chain']['manual_sections'] ?? [], 'section_number');
foreach (['6.4', '6.5'] as $expectedManual) {
    workbench_runtime_assert(
        in_array($expectedManual, $manualNumbers, true),
        'CX-03-02 缺少质量手册落实章节：' . $expectedManual
    );
}

$cx08 = Db::name('qms_structured_documents')
    ->whereLike('doc_number', 'SIM-GOV02-XZTC/CX-08%')
    ->where('version', 'GOV-TRIAL/0.2')
    ->where('soft_delete', 0)
    ->find();
workbench_runtime_assert(is_array($cx08), '8021 缺少 CX-08 GOV-TRIAL/0.2');
$cx08ViewModel = QmsFileGovernanceWorkbenchService::detail((string)$cx08['id']);
workbench_runtime_assert(
    (string)($cx08ViewModel['semantic_guard']['status'] ?? '')
        === 'suspected_mismatch'
        && (string)(
            $cx08ViewModel['semantic_guard']['issues'][0]['reason_code'] ?? ''
        ) === 'mixed_relation',
    'CX-08 的 8.3 章节正确，但要素与手册关系混装仍应被识别'
);
workbench_runtime_assert(
    ($cx08ViewModel['chain']['missing'] ?? []) === ['手册主链'],
    'CX-08 在拆分手册关系前仍应明确缺少独立手册主链'
);

foreach ([
    'CX-19' => '8.4',
    'CX-26' => '7.11',
] as $procedureCode => $expectedSection) {
    $candidate = Db::name('qms_structured_documents')
        ->whereLike('doc_number', 'SIM-GOV02-XZTC/' . $procedureCode . '%')
        ->where('version', 'GOV-TRIAL/0.2')
        ->where('soft_delete', 0)
        ->find();
    workbench_runtime_assert(is_array($candidate), '8021 缺少 ' . $procedureCode . ' GOV-TRIAL/0.2');
    $candidateViewModel = QmsFileGovernanceWorkbenchService::detail((string)$candidate['id']);
    workbench_runtime_assert(
        (string)($candidateViewModel['semantic_guard']['status'] ?? '')
            === 'suspected_mismatch'
            && (string)(
                $candidateViewModel['semantic_guard']['issues'][0]['reason_code'] ?? ''
            ) === 'mixed_relation',
        $procedureCode . ' 的候选章节正确，但混装关系仍应被识别'
    );
    workbench_runtime_assert(
        ($candidateViewModel['chain']['missing'] ?? []) === ['手册主链'],
        $procedureCode . ' 在拆分手册关系前仍应缺少独立手册主链'
    );
    $candidateManualNumbers = array_column(
        $candidateViewModel['chain']['manual_sections'] ?? [],
        'section_number'
    );
    workbench_runtime_assert(
        in_array($expectedSection, $candidateManualNumbers, true),
        $procedureCode . ' 应保留正确的候选手册章节 ' . $expectedSection
    );
}

echo "qms_file_governance_workbench_runtime_smoke passed\n";
