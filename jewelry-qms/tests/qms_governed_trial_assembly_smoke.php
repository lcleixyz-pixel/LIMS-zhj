<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\GovernedTrialAssemblyBlueprintService;
use app\service\GovernedTrialAssemblyService;
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
$templatesByCanonical = array_column($templates, null, 'canonical_doc_number');
governed_trial_assert(
    ($templatesByCanonical['XZTC/BG-35-03']['name'] ?? '') === '[治理试运行] 标准物质报废申请表',
    'BG-35-03必须按治理裁决恢复为标准物质报废申请表'
);
governed_trial_assert(
    ($templatesByCanonical['XZTC/BG-35-03']['procedure_doc_number'] ?? '') === 'XZTC/CX-03-02-2022',
    'BG-35-03必须归入标准物质管理程序'
);

foreach ($procedures as $procedure) {
    governed_trial_assert(($procedure['doc_number'] ?? '') !== '', '程序文件必须有编号');
    governed_trial_assert(($procedure['manual_sections'] ?? []) !== [], ($procedure['doc_number'] ?? '程序') . ' 必须由手册落实');
    governed_trial_assert(($procedure['record_templates'] ?? []) !== [], ($procedure['doc_number'] ?? '程序') . ' 必须有运行证明模板');
    governed_trial_assert(($procedure['source_evidence'] ?? []) !== [], ($procedure['doc_number'] ?? '程序') . ' 必须有来源依据');
}
$proceduresByNumber = array_column($procedures, null, 'doc_number');
governed_trial_assert(
    ($proceduresByNumber['XZTC/CX-08-2022']['primary_manual_section_key'] ?? '')
        === 'manual_v5_candidate_8_3',
    'CX-08 主手册关系应明确选择 8.3，不能按排序误取 4.2'
);

$procedureLinkSpecs = GovernedTrialAssemblyService::procedureLinkSpecifications(
    $proceduresByNumber['XZTC/CX-08-2022'],
    'manual-83',
    'element-document-control',
    'document-cx08',
    ['XZTC/BG-08-02' => 'template-bg0802'],
    'position-document-controller',
    ['clause-cnas-83', 'clause-cma-2121']
);
$manualSpecs = array_values(array_filter(
    $procedureLinkSpecs,
    static fn(array $spec): bool => ($spec['relation_type'] ?? '') === 'implements'
));
$recordSpecs = array_values(array_filter(
    $procedureLinkSpecs,
    static fn(array $spec): bool => ($spec['relation_type'] ?? '') === 'requires_record'
));
$positionSpecs = array_values(array_filter(
    $procedureLinkSpecs,
    static fn(array $spec): bool => ($spec['relation_type'] ?? '') === 'responsible'
));
$basisSpecs = array_values(array_filter(
    $procedureLinkSpecs,
    static fn(array $spec): bool => ($spec['relation_type'] ?? '') === 'basis'
));
governed_trial_assert(count($manualSpecs) === 1, '程序块应有一条独立手册主链');
governed_trial_assert(($manualSpecs[0]['manual_section_id'] ?? '') === 'manual-83', '程序块手册主链应指向 8.3');
governed_trial_assert(count($recordSpecs) === 1, '程序块应有一条独立运行记录关系');
governed_trial_assert(
    empty($recordSpecs[0]['manual_section_id']) && empty($recordSpecs[0]['position_id']),
    '运行记录关系不得夹带手册章节或岗位'
);
governed_trial_assert(count($positionSpecs) === 1, '责任岗位应拆成独立关系');
governed_trial_assert(count($basisSpecs) === 2, '程序块应独立回链适用外部依据');
foreach ($basisSpecs as $basisSpec) {
    governed_trial_assert(
        empty($basisSpec['manual_section_id']) && empty($basisSpec['record_form_template_id']),
        '外部依据关系不得夹带手册章节或记录模板'
    );
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
