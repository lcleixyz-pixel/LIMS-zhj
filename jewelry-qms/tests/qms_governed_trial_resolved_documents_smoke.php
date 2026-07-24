<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\GovernedTrialResolvedDocumentService;

(new think\App())->initialize();

function resolved_documents_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$manualPath = dirname(__DIR__, 2) . '/现用文件/质量手册（第四版）.docx';
$manualHashBefore = hash_file('sha256', $manualPath);
$preview = GovernedTrialResolvedDocumentService::preview();

resolved_documents_assert(count($preview['documents'] ?? []) === 38, '应生成38份连续解析稿');
resolved_documents_assert(($preview['summary']['patches_registered'] ?? 0) >= 60, '应登记已签认的批量精确补丁');
resolved_documents_assert(($preview['summary']['patches_applied'] ?? 0) >= 60, '已登记的精确补丁应实际命中基线');
resolved_documents_assert(($preview['summary']['batch_trial_ready'] ?? true) === false, '仍有未锚定签认事项时批次不得冒充就绪');

$manuals = array_values(array_filter(
    $preview['documents'] ?? [],
    static fn(array $document): bool => ($document['document_role'] ?? '') === 'quality_manual'
));
resolved_documents_assert(count($manuals) === 1, '应且仅应生成1份质量手册解析稿');
$manual = $manuals[0];
resolved_documents_assert(str_contains((string)$manual['rendered_markdown'], '附录14'), '质量手册连续稿必须包含附录14');
resolved_documents_assert(str_contains((string)$manual['rendered_markdown'], '附录15'), '质量手册连续稿必须包含附录15');
resolved_documents_assert(str_contains((string)$manual['rendered_markdown'], '附录16'), '质量手册连续稿必须包含附录16');
resolved_documents_assert(str_contains((string)$manual['rendered_markdown'], 'SIM｜治理试运行候选'), '连续稿页首必须标明SIM');
resolved_documents_assert(str_contains((string)$manual['rendered_markdown'], '纸质体系仍为唯一正式体系'), '连续稿必须声明非正式文件边界');
resolved_documents_assert(!str_contains((string)$manual['resolved_body'], 'XZTC/CX-08-2018'), '已签认2018引用清扫应进入解析稿');

$cx21 = array_values(array_filter(
    $preview['documents'] ?? [],
    static fn(array $document): bool => ($document['doc_number'] ?? '') === 'XZTC/CX-21-2022'
))[0] ?? [];
resolved_documents_assert(str_contains((string)($cx21['resolved_body'] ?? ''), '3.1 总经理（最高管理者）应：'), 'CX-21批准链修订应进入连续稿');
resolved_documents_assert(str_contains((string)($cx21['resolved_body'] ?? ''), 'XZTC/BG-21-04'), 'CX-21正确记录清单应进入连续稿');
resolved_documents_assert(!str_contains((string)($cx21['resolved_body'] ?? ''), '《年度内审计划表》'), 'CX-21不应继续混挂内审记录');

$cx01 = array_values(array_filter(
    $preview['documents'] ?? [],
    static fn(array $document): bool => ($document['doc_number'] ?? '') === 'XZTC/CX-01-2022'
))[0] ?? [];
resolved_documents_assert(
    str_contains((string)($cx01['resolved_body'] ?? ''), '《检验检测机构资质认定评审准则》（市场监管总局公告2023年第21号）'),
    '人员培训程序应改用现行CMA评审准则'
);
resolved_documents_assert(
    str_contains((string)($cx01['resolved_body'] ?? ''), 'RB/T 214-2017仅作为历史制度衔接和辅助参考，不作为现行CMA主依据'),
    '人员培训程序应明确RB/T 214的历史辅助身份'
);

$cx0102 = array_values(array_filter(
    $preview['documents'] ?? [],
    static fn(array $document): bool => ($document['doc_number'] ?? '') === 'XZTC/CX-01-02-2022'
))[0] ?? [];
resolved_documents_assert(
    str_contains((string)($cx0102['resolved_body'] ?? ''), 'RB/T 214-2017仅作历史制度衔接和辅助参考，不作为现行CMA主依据'),
    '人员管理程序应明确RB/T 214的历史辅助身份'
);
resolved_documents_assert(
    ($preview['summary']['warnings'] ?? -1) === 0,
    '依据身份修订后RB/T 214提醒应归零'
);

foreach ($preview['documents'] ?? [] as $document) {
    resolved_documents_assert(
        preg_match('/XZTC\\/CX-[0-9]+(?:-[0-9]+)?-2018/u', (string)$document['resolved_body']) !== 1,
        (string)$document['doc_number'] . ' 不应继续保留已签认清扫范围内的2018程序引用'
    );
}

$output = sys_get_temp_dir() . '/qms-governed-resolved-' . getmypid();
$written = GovernedTrialResolvedDocumentService::writePackage($preview, $output);
resolved_documents_assert(is_file($output . '/装配清单.json'), '应生成装配清单JSON');
resolved_documents_assert(is_file($output . '/冲突审查/冲突总表.md'), '应生成冲突总表Markdown');
resolved_documents_assert(is_file($output . '/冲突审查/冲突总表.json'), '应生成冲突总表JSON');
resolved_documents_assert(is_file($output . '/验证报告.md'), '应生成验证报告');
resolved_documents_assert(is_file($output . '/版本台账.md'), '应生成版本台账');
resolved_documents_assert(count(glob($output . '/连续正文/*.md') ?: []) === 38, '应落盘38份连续Markdown');
resolved_documents_assert(count(glob($output . '/连续正文/*.html') ?: []) === 38, '应落盘38份连续HTML');
resolved_documents_assert(count(glob($output . '/修订对照/*.md') ?: []) === 38, '每份文件均应有修订对照');
resolved_documents_assert(($written['document_count'] ?? 0) === 38, '落盘结果应报告38份文件');
resolved_documents_assert(hash_file('sha256', $manualPath) === $manualHashBefore, '生成解析稿不得改动现用质量手册原件');

echo "qms_governed_trial_resolved_documents_smoke passed\n";
