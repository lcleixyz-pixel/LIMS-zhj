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
resolved_documents_assert(($preview['summary']['patches_registered'] ?? 0) >= 15, '应登记已签认的精确补丁');
resolved_documents_assert(
    ($preview['summary']['patches_applied'] ?? -1) === ($preview['summary']['patches_registered'] ?? 0),
    '已登记的精确补丁应全部实际命中基线'
);
resolved_documents_assert(($preview['summary']['batch_trial_ready'] ?? false) === true, '试运行关键阻断关闭后批次应可进入SIM链路');
resolved_documents_assert(($preview['summary']['blocking_conflicts'] ?? -1) === 0, '试运行关键阻断应归零');
resolved_documents_assert(($preview['summary']['warnings'] ?? 0) >= 1, '剩余逐项正文治理应继续公开为非阻断待办');

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
resolved_documents_assert(
    str_contains((string)$manual['resolved_body'], '本公司当前不开展抽样'),
    '手册7.3应明确当前不开展抽样'
);
resolved_documents_assert(
    !str_contains((string)$manual['resolved_body'], '《抽样控制程序》 XZTC/CX-35-2022'),
    '手册7.3不应继续把CX-35列为现行支持性文件'
);
foreach (['## 附录14：程序文件目录', '## 附录15：各岗位任职资格条件', '## 附录16：质量手册条款对照表'] as $heading) {
    resolved_documents_assert(str_contains((string)$manual['resolved_body'], $heading), $heading . ' 应成为结构化标题');
}

$cx35 = array_values(array_filter(
    $preview['documents'] ?? [],
    static fn(array $document): bool => ($document['doc_number'] ?? '') === 'XZTC/CX-35-2022'
))[0] ?? [];
resolved_documents_assert(($cx35['status'] ?? '') === 'obsolete', 'CX-35试运行解析稿应标为作废保留');
resolved_documents_assert(
    str_contains((string)($cx35['resolved_body'] ?? ''), '本公司当前不开展抽样'),
    'CX-35应改为不适用与历史追溯说明'
);
resolved_documents_assert(
    !str_contains((string)($cx35['resolved_body'] ?? ''), '4.3.7将抽取样品分为两份'),
    'CX-35作废保留稿不得继续呈现可执行抽样步骤'
);

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
$warningTypes = array_column($preview['warnings'] ?? [], 'type');
resolved_documents_assert(
    !in_array('legacy_cma_basis_review', $warningTypes, true),
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

$acceptancePath = $output . '/实施验收记录.md';
file_put_contents($acceptancePath, "验收记录应由装配保留\n");
GovernedTrialResolvedDocumentService::writePackage($preview, $output);
resolved_documents_assert(
    is_file($acceptancePath) && file_get_contents($acceptancePath) === "验收记录应由装配保留\n",
    '重复装配不得删除人工验收记录'
);

echo "qms_governed_trial_resolved_documents_smoke passed\n";
