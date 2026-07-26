<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\QmsCx0302GovernanceClosureService;
use app\service\GovernedTrialResolvedDocumentService;
use think\facade\Db;

(new think\App())->initialize();

function cx0302_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$preview = QmsCx0302GovernanceClosureService::preview();
cx0302_runtime_assert(($preview['ready_to_apply'] ?? false) === true, 'CX-03-02 写入前预检存在阻断');
cx0302_runtime_assert(
    ($preview['target']['doc_number'] ?? '') === QmsCx0302GovernanceClosureService::STRUCTURE_DOC_NUMBER,
    '预检目标不是 CX-03-02 GOV-TRIAL/0.2'
);
cx0302_runtime_assert(
    count($preview['schema']['expected_keys'] ?? []) === 19,
    'BG-35-03 应有19个纸质源表字段'
);
cx0302_runtime_assert(
    count($preview['planned_links'] ?? []) === 5,
    'CX-03-02 应定向落实2条手册关系和3条外部依据关系'
);
$resolvedVerification = GovernedTrialResolvedDocumentService::verifyDatabaseAssembly();
cx0302_runtime_assert(
    ($resolvedVerification['ok'] ?? false) === true,
    '记录表 schema 不得被误计入0.1手册/程序世系数量：'
        . implode('；', $resolvedVerification['errors'] ?? [])
);

if (getenv('QMS_CX0302_APPLY') === '1') {
    $first = QmsCx0302GovernanceClosureService::apply();
    cx0302_runtime_assert(($first['verification']['ok'] ?? false) === true, '首次定向闭环后验证失败');

    $beforeSecond = [
        'assets' => (int)Db::name('qms_document_assets')->where('soft_delete', 0)->count(),
        'structures' => (int)Db::name('qms_structured_documents')->where('soft_delete', 0)->count(),
        'links' => (int)Db::name('qms_document_block_links')->where('soft_delete', 0)->count(),
    ];
    $second = QmsCx0302GovernanceClosureService::apply();
    $afterSecond = [
        'assets' => (int)Db::name('qms_document_assets')->where('soft_delete', 0)->count(),
        'structures' => (int)Db::name('qms_structured_documents')->where('soft_delete', 0)->count(),
        'links' => (int)Db::name('qms_document_block_links')->where('soft_delete', 0)->count(),
    ];
    cx0302_runtime_assert(($second['verification']['ok'] ?? false) === true, '重复定向闭环后验证失败');
    cx0302_runtime_assert($beforeSecond === $afterSecond, '重复执行不得增加活动资产、结构化文件或追溯关系');
}

echo "qms_cx0302_governance_closure_runtime_smoke passed\n";
