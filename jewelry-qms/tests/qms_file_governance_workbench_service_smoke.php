<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\service\GovernedTrialResolvedDocumentService;

function workbench_service_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__);
$structureSource = (string)file_get_contents($root . '/app/service/QmsDocumentStructureService.php');
$resolvedSource = (string)file_get_contents($root . '/app/service/GovernedTrialResolvedDocumentService.php');

preg_match(
    '/private static function linksForBlock\\(string \\$blockId\\): array.*?->toArray\\(\\);/s',
    $structureSource,
    $linksMethod
);
workbench_service_assert($linksMethod !== [], '未找到 linksForBlock 方法源码');
foreach ([
    'l.clause_id',
    'l.manual_section_id',
    'l.procedure_document_id',
    'l.record_form_template_id',
    'l.position_id',
    'l.business_module_id',
] as $requiredField) {
    workbench_service_assert(
        str_contains((string)$linksMethod[0], $requiredField),
        '块级追溯结果缺少对象 ID：' . $requiredField
    );
}

workbench_service_assert(
    str_contains($resolvedSource, 'public static function currentConflictSummary'),
    '治理解析稿服务应提供只读冲突摘要'
);

$summary = GovernedTrialResolvedDocumentService::splitConflictSummary([
    'blocking_conflicts' => [
        ['doc_number' => 'XZTC/CX-03-02-2022', 'message' => '当前文件冲突'],
        ['doc_number' => 'XZTC/CX-21-2022', 'message' => '其他文件冲突'],
    ],
    'warnings' => [
        ['doc_number' => 'SYSTEM', 'message' => '体系提醒'],
    ],
], 'XZTC/CX-03-02-2022');

workbench_service_assert(count($summary['document_blockers']) === 1, '应只保留当前文件阻断');
workbench_service_assert(count($summary['system_notices']) === 2, '其他文件冲突和体系提醒应归入体系提示');

echo "qms_file_governance_workbench_service_smoke contract passed\n";
