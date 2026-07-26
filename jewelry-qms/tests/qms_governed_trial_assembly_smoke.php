<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\GovernedTrialAssemblyBlueprintService;
use app\service\RecordFormPrintService;
use app\service\TrialModeService;

(new think\App())->initialize();

function governed_trial_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

governed_trial_assert(
    class_exists(GovernedTrialAssemblyBlueprintService::class),
    '治理试运行装配蓝图服务尚未实现'
);

$blueprint = GovernedTrialAssemblyBlueprintService::build();
$templates = $blueprint['record_templates'] ?? [];
$procedures = $blueprint['procedures'] ?? [];
$manualSections = $blueprint['manual_sections'] ?? [];
$sources = $blueprint['sources'] ?? [];

governed_trial_assert(($blueprint['trial_batch'] ?? '') === 'GOV-TRIAL-20260724', '治理试运行批次号固定');
governed_trial_assert(count($templates) === 104, '活动记录电子母版应明确归一为104份');
governed_trial_assert(count($procedures) === 37, '应装配37份2022版程序');
governed_trial_assert(count($manualSections) === 29, '应装配29个手册结构块');
governed_trial_assert(count($sources) >= 10, '应固化法规、手册、G1和G2来源依据');

$canonicalNumbers = [];
$trialNumbers = [];
foreach ($templates as $template) {
    $canonical = (string)($template['canonical_doc_number'] ?? '');
    $trial = (string)($template['doc_number'] ?? '');
    governed_trial_assert($canonical !== '', '每份模板都有电子母版编号');
    governed_trial_assert(str_starts_with($trial, 'SIM-'), $canonical . ' 必须使用SIM编号');
    governed_trial_assert(($template['status'] ?? '') === 'trial_ready', $canonical . ' 必须是试运行就绪状态');
    governed_trial_assert(($template['version'] ?? '') === 'GOV-TRIAL/0.1', $canonical . ' 必须使用治理试运行版本');
    governed_trial_assert(($template['retention_period'] ?? '') === '不少于6年', $canonical . ' 必须声明保存期限');
    governed_trial_assert(($template['applicable_sites'] ?? '') !== '', $canonical . ' 必须声明适用场所');
    governed_trial_assert(($template['responsible_position_code'] ?? '') !== '', $canonical . ' 必须声明责任岗位');
    governed_trial_assert(($template['procedure_doc_number'] ?? '') !== '', $canonical . ' 必须映射程序文件');
    governed_trial_assert(($template['manual_section_key'] ?? '') !== '', $canonical . ' 必须回溯手册章节');
    governed_trial_assert(($template['source_evidence'] ?? []) !== [], $canonical . ' 必须保留来源依据');
    governed_trial_assert(($template['field_schema'] ?? []) !== [], $canonical . ' 必须具备字段结构');
    governed_trial_assert(($template['print_template_key'] ?? '') !== '', $canonical . ' 必须具备打印模板');
    $canonicalNumbers[] = $canonical;
    $trialNumbers[] = $trial;
}

governed_trial_assert(count(array_unique($canonicalNumbers)) === 104, '电子母版编号不得重复');
governed_trial_assert(count(array_unique($trialNumbers)) === 104, 'SIM模板编号不得重复');
governed_trial_assert(!in_array('XZTC/BG-10-01', $canonicalNumbers, true), '已作废BG-10-01不得进入活动模板');
governed_trial_assert(!in_array('XZTC/BG-10-02', $canonicalNumbers, true), '已作废BG-10-02不得进入活动模板');

foreach ($procedures as $procedure) {
    governed_trial_assert(($procedure['doc_number'] ?? '') !== '', '程序文件必须有编号');
    governed_trial_assert(($procedure['manual_sections'] ?? []) !== [], ($procedure['doc_number'] ?? '程序') . ' 必须由手册落实');
    governed_trial_assert(($procedure['record_templates'] ?? []) !== [], ($procedure['doc_number'] ?? '程序') . ' 必须有运行证明模板');
    governed_trial_assert(($procedure['source_evidence'] ?? []) !== [], ($procedure['doc_number'] ?? '程序') . ' 必须有来源依据');
}

foreach ($manualSections as $section) {
    governed_trial_assert(($section['section_key'] ?? '') !== '', '手册结构块必须有章节键');
    governed_trial_assert(($section['external_sources'] ?? []) !== [], ($section['section_key'] ?? '手册章节') . ' 必须关联外部依据');
    governed_trial_assert(($section['procedure_doc_numbers'] ?? []) !== [], ($section['section_key'] ?? '手册章节') . ' 必须落实到程序文件');
}

foreach ($sources as $source) {
    governed_trial_assert(is_file((string)($source['absolute_path'] ?? '')), ($source['source_key'] ?? '来源') . ' 原件必须存在');
    governed_trial_assert(
        preg_match('/^[a-f0-9]{64}$/', (string)($source['sha256'] ?? '')) === 1,
        ($source['source_key'] ?? '来源') . ' 必须有SHA-256'
    );
}

$validation = GovernedTrialAssemblyBlueprintService::validate($blueprint);
governed_trial_assert(($validation['ok'] ?? false) === true, '装配蓝图必须通过自检：' . implode('；', $validation['errors'] ?? []));

foreach ($templates as $template) {
    foreach (['wulumuqi', 'hetian'] as $site) {
        $html = RecordFormPrintService::render(
            (string)$template['print_template_key'],
            $template,
            ['usage_site' => $site]
        );
        $html = TrialModeService::watermarkHtml($html, true);
        governed_trial_assert(
            str_contains($html, '试运行/非正式受控副本'),
            (string)$template['canonical_doc_number'] . ' 必须带试运行水印'
        );
        governed_trial_assert(
            strlen($html) > 500,
            (string)$template['canonical_doc_number'] . ' 必须能生成可打印内容'
        );
    }
}

echo "qms_governed_trial_assembly_smoke passed\n";
