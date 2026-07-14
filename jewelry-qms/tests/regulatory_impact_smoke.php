<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use app\service\regulatory\HtmlListSourceAdapter;
use app\service\regulatory\RegulatoryImpactService;
use app\service\regulatory\RegulatorySourceRegistry;

function impact_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function impact_assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . PHP_EOL
            . 'Expected: ' . var_export($expected, true) . PHP_EOL
            . 'Actual: ' . var_export($actual, true)
        );
    }
}

function impact_canonical(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }
    foreach ($value as $key => $item) {
        $value[$key] = impact_canonical($item);
    }
    if (!array_is_list($value)) {
        ksort($value);
    }

    return $value;
}

$impactKeys = [
    'cma_scope_mark',
    'qms_documents',
    'personnel_authorization',
    'equipment_calibration',
    'lims_rules',
    'training',
];
$contextCalls = 0;
$contextProvider = static function (string $companyId) use (&$contextCalls): array {
    $contextCalls++;
    impact_assert_same('company-impact-smoke', $companyId, 'Context provider must be scoped to the candidate company');

    return [
        'qms_structure_layers' => ['external_basis', 'quality_manual', 'procedure', 'work_instruction', 'record_form'],
        'active_employee_count' => 5,
        'active_personnel_authorization_count' => 3,
        'active_equipment_count' => 8,
        'active_equipment_authorization_count' => 4,
    ];
};
$service = new RegulatoryImpactService($contextProvider);
$registry = new RegulatorySourceRegistry();
$source = $registry->source('samr_rkjcs_notice');
$fixture = (string)file_get_contents(__DIR__ . '/fixtures/regulatory/samr_one_list_one_library.html');
$parsed = (new HtmlListSourceAdapter())->parse($fixture, $source);
$positiveItem = $parsed['items'][0];
impact_assert(
    str_contains($positiveItem['title'], '一单一库'),
    'Positive fixture must be the approved one-list-one-library SAMR case rather than a generic capability notice'
);
impact_assert(
    str_contains((string)$positiveItem['announcement_number'], '第 14 号'),
    'Positive fixture must retain SAMR announcement No. 14 of 2026'
);
$positive = $service->analyze($positiveItem, 'company-impact-smoke');

impact_assert_same($impactKeys, array_keys($positive['impact_analysis']), 'Impact analysis must always use the fixed six-key order');
impact_assert(is_string($positive['analysis_rule_version']) && $positive['analysis_rule_version'] !== '', 'Rule version must be explicit');
impact_assert(
    is_float($positive['analysis_confidence'])
        && $positive['analysis_confidence'] >= 0.0
        && $positive['analysis_confidence'] <= 1.0,
    'Overall confidence must be bounded'
);
impact_assert(is_string($positive['analysis_rationale']) && $positive['analysis_rationale'] !== '', 'Overall rationale must be non-empty');
impact_assert_same(1, $contextCalls, 'Read-only context provider must be called exactly once per analysis');

foreach ($positive['impact_analysis'] as $impactKey => $assessment) {
    impact_assert_same(
        ['conclusion', 'evidence', 'rule_ids', 'confidence'],
        array_keys($assessment),
        'Each impact key must use the fixed field order: ' . $impactKey
    );
    impact_assert(
        in_array($assessment['conclusion'], ['likely', 'possible', 'no_match'], true),
        'Conclusion enum is invalid: ' . $impactKey
    );
    impact_assert(is_array($assessment['evidence']) && $assessment['evidence'] !== [], 'Evidence must be non-empty: ' . $impactKey);
    impact_assert(is_array($assessment['rule_ids']), 'rule_ids must be an array: ' . $impactKey);
    impact_assert(
        is_float($assessment['confidence'])
            && $assessment['confidence'] >= 0.0
            && $assessment['confidence'] <= 1.0,
        'Per-impact confidence must be bounded: ' . $impactKey
    );
}
$requiredRules = [
    'cma_scope_mark' => 'REG-CMA-ONE-LIST-001',
    'qms_documents' => 'REG-QMS-ONE-LIST-001',
    'personnel_authorization' => 'REG-PER-ONE-LIST-001',
    'lims_rules' => 'REG-LIMS-ONE-LIST-001',
];
foreach ($requiredRules as $requiredHit => $requiredRuleId) {
    impact_assert(
        in_array($positive['impact_analysis'][$requiredHit]['conclusion'], ['likely', 'possible'], true),
        'One-list-one-library fixture must hit required impact: ' . $requiredHit
    );
    impact_assert(
        in_array($requiredRuleId, $positive['impact_analysis'][$requiredHit]['rule_ids'], true),
        'One-list-one-library hit must identify its specific rule_id: ' . $requiredHit
    );
    impact_assert(
        str_contains(implode(' ', $positive['impact_analysis'][$requiredHit]['evidence']), '一单一库')
            || str_contains(implode(' ', $positive['impact_analysis'][$requiredHit]['evidence']), '资质认定事项清单')
            || str_contains(implode(' ', $positive['impact_analysis'][$requiredHit]['evidence']), '质量管理体系文件')
            || str_contains(implode(' ', $positive['impact_analysis'][$requiredHit]['evidence']), '人员授权范围')
            || str_contains(implode(' ', $positive['impact_analysis'][$requiredHit]['evidence']), '统一数据规则'),
        'Required hit must quote normalized input evidence: ' . $requiredHit
    );
}

$unrelated = [
    'title' => '关于机关食堂绿化养护安排的通知',
    'announcement_number' => '后勤〔2026〕7号',
    'document_type' => '后勤通知',
    'summary' => '安排办公区域绿植浇水和食堂值班。',
    'evidence' => ['raw_text' => '本通知仅涉及办公区域绿化养护和食堂排班。'],
];
$negative = $service->analyze($unrelated, 'company-impact-smoke');
impact_assert_same($impactKeys, array_keys($negative['impact_analysis']), 'Negative analysis must retain all six keys');
foreach ($negative['impact_analysis'] as $impactKey => $assessment) {
    impact_assert_same('no_match', $assessment['conclusion'], 'Unrelated notice must be no_match: ' . $impactKey);
    impact_assert_same([], $assessment['rule_ids'], 'no_match must have no rule ids: ' . $impactKey);
    $wording = implode(' ', $assessment['evidence']);
    impact_assert(str_contains($wording, '未命中') && str_contains($wording, '人工复核'), 'no_match must explain rule miss and manual review');
    impact_assert(!str_contains($wording, '不适用'), 'no_match must never be worded as not applicable');
}
impact_assert(!str_contains(json_encode($negative, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), '不适用'), 'Negative result must not contain not-applicable wording');

$stableA = $service->analyze($positiveItem, 'company-impact-smoke');
$stableB = $service->analyze($positiveItem, 'company-impact-smoke');
impact_assert_same(impact_canonical($stableA), impact_canonical($stableB), 'Repeated analysis must be deterministic');
$timeVariant = $positiveItem;
$timeVariant['fetched_at'] = '2026-07-14 09:00:00';
$timeVariant['first_seen_at'] = '2026-07-14 09:00:01';
$timeVariant['evidence']['fetched_at'] = '2026-07-14 09:00:02';
$timeVariant['evidence']['metadata'] = [
    'last_seen_at' => '2026-07-15 10:00:00',
    'observed_at' => '2026-07-15 10:00:01',
];
$withObservationTimes = $service->analyze($timeVariant, 'company-impact-smoke');
impact_assert_same(
    impact_canonical($stableA),
    impact_canonical($withObservationTimes),
    'Observation timestamps must not enter deterministic evidence or rule results'
);
$envNames = ['OPENAI_API_KEY', 'ANTHROPIC_API_KEY', 'DEEPSEEK_API_KEY'];
$savedEnv = [];
foreach ($envNames as $envName) {
    $savedEnv[$envName] = getenv($envName);
    putenv($envName . '=should-not-affect-deterministic-rules');
}
$withAiEnv = $service->analyze($positiveItem, 'company-impact-smoke');
foreach ($envNames as $envName) {
    if ($savedEnv[$envName] === false) {
        putenv($envName);
    } else {
        putenv($envName . '=' . $savedEnv[$envName]);
    }
}
impact_assert_same(impact_canonical($stableA), impact_canonical($withAiEnv), 'AI environment variables must not affect deterministic output');

$missingContextService = new RegulatoryImpactService(
    static function (string $companyId): array {
        throw new RuntimeException('offline context fixture');
    }
);
$missingContext = $missingContextService->analyze($positiveItem, 'company-impact-smoke');
impact_assert(
    $missingContext['analysis_confidence'] < $positive['analysis_confidence'],
    'Missing organization context must lower overall confidence'
);
impact_assert(
    str_contains(json_encode($missingContext, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), '数据未取得，需人工确认'),
    'Missing context must degrade to explicit human confirmation'
);
impact_assert_same($impactKeys, array_keys($missingContext['impact_analysis']), 'Missing context must not break six-key contract');

echo "regulatory_impact_smoke passed\n";
