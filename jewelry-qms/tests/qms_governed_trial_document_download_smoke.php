<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\GovernedTrialResolvedDocumentService;
use think\facade\Db;

$app = new think\App();
$app->initialize();

function governed_download_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

governed_download_assert(
    method_exists(GovernedTrialResolvedDocumentService::class, 'resolveDownloadPath'),
    '治理解析稿服务必须提供受限下载路径解析'
);

$filePath = (string)Db::name('documents')
    ->whereLike('doc_number', 'SIM-GOV02-%')
    ->where('version', 'GOV-TRIAL/0.2')
    ->where('soft_delete', 0)
    ->where('file_path', '<>', '')
    ->value('file_path');
governed_download_assert($filePath !== '', '缺少可验证的 GOV-TRIAL/0.2 附件路径');

$resolved = GovernedTrialResolvedDocumentService::resolveDownloadPath($filePath);
governed_download_assert(
    is_string($resolved) && $resolved !== '' && is_file($resolved),
    '治理解析稿附件应解析到真实文件'
);
governed_download_assert(
    GovernedTrialResolvedDocumentService::resolveDownloadPath('/etc/passwd') === null,
    '不得解析治理输出目录外的绝对路径'
);
governed_download_assert(
    GovernedTrialResolvedDocumentService::resolveDownloadPath(
        GovernedTrialResolvedDocumentService::DEFAULT_OUTPUT . '/../版本台账.md'
    ) === null,
    '不得通过目录穿越下载治理输出目录外文件'
);

$controller = (string)file_get_contents(__DIR__ . '/../app/controller/Document.php');
governed_download_assert(
    str_contains($controller, 'GovernedTrialResolvedDocumentService::resolveDownloadPath')
        && str_contains($controller, 'FileService::downloadAbsolute'),
    '文件下载控制器必须使用受限治理路径和绝对路径下载'
);

echo "qms_governed_trial_document_download_smoke passed\n";
