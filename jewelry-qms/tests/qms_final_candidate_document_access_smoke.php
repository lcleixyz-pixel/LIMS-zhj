<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\FinalCandidateAssemblyService;
use app\service\QmsDocumentStructureService;
use app\service\QmsReadableMarkdownService;
use app\service\TrialModeService;
use think\facade\Db;

(new think\App())->initialize();

function final_candidate_access_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$samples = [
    'SIM-GOV03-XZTC/SC-2026',
    'SIM-GOV03-XZTC/CX-08-2026',
    'SIM-GOV03-XZTC/CX-26-2026',
    'SIM-GOV03-XZTC/ZY-1-01-2026',
    'SIM-GOV03-XZTC/ZY-4-01-2026',
];
foreach ($samples as $number) {
    $document = Db::name('documents')
        ->where('doc_number', $number)
        ->where('version', 'GOV-TRIAL/0.3')
        ->where('soft_delete', 0)
        ->find();
    final_candidate_access_assert(is_array($document), $number . ' 必须存在');
    $resolved = FinalCandidateAssemblyService::resolveOutputPath((string)$document['file_path']);
    final_candidate_access_assert(is_string($resolved) && is_file($resolved), $number . ' 候选正文必须可受限解析');
    $markdown = file_get_contents($resolved);
    final_candidate_access_assert(is_string($markdown) && str_contains($markdown, '8021候选试装'), $number . ' 正文必须带候选边界');
    $html = QmsReadableMarkdownService::toHtml($markdown);
    final_candidate_access_assert(str_contains($html, '8021候选试装'), $number . ' Markdown必须可转成打印HTML');
    $summary = QmsDocumentStructureService::controlledDocumentStructureSummary((string)$document['id']);
    final_candidate_access_assert($summary !== [], $number . ' 文件详情必须能读取结构化摘要');
}

final_candidate_access_assert(FinalCandidateAssemblyService::resolveOutputPath('/etc/passwd') === null, '不得解析候选目录外绝对路径');
final_candidate_access_assert(FinalCandidateAssemblyService::resolveOutputPath('.team/交接箱/2026-08-20-8021候选试装/../其他文件') === null, '不得目录穿越');
final_candidate_access_assert(
    FinalCandidateAssemblyService::isCandidateIdentity('SIM-GOV03-XZTC/CX-08-2026', 'GOV-TRIAL/0.3'),
    '0.3候选身份必须可被统一识别'
);
final_candidate_access_assert(
    !FinalCandidateAssemblyService::isCandidateIdentity('SIM-GOV02-XZTC/CX-08-2022', 'GOV-TRIAL/0.2'),
    '既有0.2试运行文件不得误判为0.3候选'
);

$candidateApprovalBlocked = false;
try {
    TrialModeService::assertDocumentApprovalAllowed([
        'doc_number' => 'SIM-GOV03-XZTC/CX-08-2026',
        'version' => 'GOV-TRIAL/0.3',
    ]);
} catch (\DomainException $exception) {
    $candidateApprovalBlocked = str_contains($exception->getMessage(), '候选');
}
final_candidate_access_assert($candidateApprovalBlocked, '0.3候选必须阻断审核、批准和发布');
TrialModeService::assertDocumentApprovalAllowed([
    'doc_number' => 'SIM-GOV02-XZTC/CX-08-2022',
    'version' => 'GOV-TRIAL/0.2',
]);

$controller = (string)file_get_contents(__DIR__ . '/../app/controller/Document.php');
final_candidate_access_assert(str_contains($controller, 'function candidatePreview'), '文件控制器必须提供只读候选预览入口');
final_candidate_access_assert(str_contains($controller, 'FinalCandidateAssemblyService::resolveOutputPath'), '候选预览和下载必须使用受限路径解析');
final_candidate_access_assert(str_contains($controller, 'TrialModeService::assertDocumentApprovalAllowed($doc)'), '提交审核入口必须复用候选审批门禁');
$view = (string)file_get_contents(__DIR__ . '/../app/view/document/candidate_preview.html');
final_candidate_access_assert(str_contains($view, 'window.print()'), '候选预览必须支持浏览器打印');
final_candidate_access_assert(str_contains($view, '候选试装') && str_contains($view, '非正式'), '候选预览必须显示非正式水印');
final_candidate_access_assert(!str_contains($view, 'controlledPrint'), '候选预览不得伪装成受控打印');
$routes = (string)file_get_contents(__DIR__ . '/../route/app.php');
final_candidate_access_assert(str_contains($routes, "document/candidatePreview', 'Document/candidatePreview"), '候选预览入口必须登记显式只读路由');

echo "qms_final_candidate_document_access_smoke passed\n";
