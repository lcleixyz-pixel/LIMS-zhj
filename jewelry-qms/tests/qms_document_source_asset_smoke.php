<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\DocumentOperationModeService;
use app\service\DocumentSourceAssetService;

(new think\App())->initialize();

function document_source_asset_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$mode = DocumentOperationModeService::presentation();
document_source_asset_assert(($mode['mode'] ?? '') === 'paper_governance', '8021 当前必须固定为纸质运行治理阶段');
document_source_asset_assert(str_contains((string)($mode['label'] ?? ''), '纸质执行'), '运行阶段必须用人话标识纸质执行');
document_source_asset_assert(($mode['is_electronic_controlled'] ?? true) === false, '本轮不得误标电子受控');

$sourceDir = trim((string)getenv('QMS_FINAL_CANDIDATE_SNAPSHOT_DIR'));
document_source_asset_assert($sourceDir !== '' && is_dir($sourceDir), '必须提供65份来源Word快照目录');

$preview = DocumentSourceAssetService::previewFinalCandidateSnapshot($sourceDir);
document_source_asset_assert(($preview['mode'] ?? '') === 'inspect_only', '来源资产预览不得写库');
document_source_asset_assert(($preview['validation']['ok'] ?? false) === true, '65份来源资产必须全部匹配候选文件');
document_source_asset_assert(($preview['counts']['source_files'] ?? 0) === 65, '来源快照必须恰好65份Word');
document_source_asset_assert(($preview['counts']['matched_documents'] ?? 0) === 65, '65份Word必须全部匹配候选对象');

$sample = $preview['items'][0] ?? [];
$resolved = DocumentSourceAssetService::resolveSourcePath(
    (string)($sample['stored_path'] ?? ''),
    (string)($sample['source_sha256'] ?? '')
);
document_source_asset_assert(is_string($resolved) && is_file($resolved), '白名单来源路径和哈希必须可解析');
document_source_asset_assert(DocumentSourceAssetService::resolveSourcePath('/etc/passwd', hash('sha256', 'x')) === null, '不得解析快照目录外绝对路径');
document_source_asset_assert(DocumentSourceAssetService::resolveSourcePath('../源文件快照/伪造.docx', hash('sha256', 'x')) === null, '不得目录穿越');
document_source_asset_assert(DocumentSourceAssetService::resolveSourcePath((string)($sample['stored_path'] ?? ''), str_repeat('0', 64)) === null, '哈希不一致必须拒绝下载');

$command = (string)file_get_contents(__DIR__ . '/../app/command/QmsFinalCandidateSourceAssets.php');
document_source_asset_assert(str_contains($command, 'qms:link-final-source-assets'), '必须提供来源资产补链命令');
document_source_asset_assert(str_contains($command, 'ack-8021-source-assets'), '来源资产写入必须要求确认参数');

echo "qms_document_source_asset_smoke passed\n";
