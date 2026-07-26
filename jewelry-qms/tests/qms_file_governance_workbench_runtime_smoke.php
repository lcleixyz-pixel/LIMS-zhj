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

echo "qms_file_governance_workbench_runtime_smoke passed\n";
