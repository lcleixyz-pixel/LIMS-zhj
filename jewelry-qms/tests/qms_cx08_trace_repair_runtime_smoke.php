<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/app/common.php';

use app\service\QmsFileGovernanceWorkbenchService;
use think\facade\Db;

(new think\App())->initialize();

function cx08_trace_repair_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$structured = Db::name('qms_structured_documents')
    ->where('doc_number', 'SIM-GOV02-XZTC/CX-08-2022')
    ->where('version', 'GOV-TRIAL/0.2')
    ->where('soft_delete', 0)
    ->find();
cx08_trace_repair_assert(is_array($structured), '8021 缺少 CX-08 GOV-TRIAL/0.2');

$viewModel = QmsFileGovernanceWorkbenchService::detail((string)$structured['id']);
cx08_trace_repair_assert(
    (string)($viewModel['semantic_guard']['status'] ?? '') === 'aligned',
    'CX-08 应以 8.3 作为已确认手册主链'
);
cx08_trace_repair_assert(
    ($viewModel['chain']['missing'] ?? []) === [],
    'CX-08 的外部依据、手册、程序和运行证据主链应闭合'
);

$blockIds = Db::name('qms_document_blocks')
    ->where('structured_document_id', (string)$structured['id'])
    ->where('soft_delete', 0)
    ->column('id');
$mixedCount = Db::name('qms_document_block_links')
    ->whereIn('block_id', $blockIds)
    ->whereNotNull('manual_section_id')
    ->whereNotNull('record_form_template_id')
    ->where('soft_delete', 0)
    ->count();
cx08_trace_repair_assert((int)$mixedCount === 0, 'CX-08 不得保留手册与记录混装关系');

$manualNumbers = array_column(
    $viewModel['chain']['confirmed_manual_sections'] ?? [],
    'section_number'
);
cx08_trace_repair_assert(in_array('8.3', $manualNumbers, true), 'CX-08 应确认手册 8.3');

$externalLabels = array_map(
    static fn(array $row): string =>
        (string)($row['source_code'] ?? '') . ' ' . (string)($row['clause_number'] ?? ''),
    $viewModel['chain']['confirmed_external_sources'] ?? []
);
foreach ([
    'CNAS-CL01:2018 8.3',
    '市场监管总局公告2023年第21号 2.12.1',
] as $expectedExternal) {
    cx08_trace_repair_assert(
        in_array($expectedExternal, $externalLabels, true),
        'CX-08 缺少外部依据主链：' . $expectedExternal
    );
}

$recordNumbers = array_column(
    $viewModel['chain']['confirmed_record_evidence'] ?? [],
    'doc_number'
);
cx08_trace_repair_assert(
    in_array('SIM-XZTC/BG-08-02', $recordNumbers, true),
    'CX-08 应确认 BG-08-02 运行证据'
);

echo "qms_cx08_trace_repair_runtime_smoke passed\n";
