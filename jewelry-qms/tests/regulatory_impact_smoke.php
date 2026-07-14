<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\regulatory\HtmlListSourceAdapter;
use app\service\regulatory\RegulatoryImpactService;
use app\service\regulatory\RegulatorySourceRegistry;
use think\facade\Db;

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
impact_assert_same(
    '市场监管总局关于对检验检测机构资质认定实施“一单一库”管理的公告',
    $positiveItem['title'],
    'Positive fixture title must match the official SAMR announcement'
);
impact_assert_same('2026年第14号', $positiveItem['announcement_number'], 'Official announcement number mismatch');
impact_assert_same('2026-04-23', $positiveItem['published_date'], 'Official publication date mismatch');
impact_assert(str_contains($positiveItem['summary'], '资质认定事项清单'), 'Official facts must include the accreditation item list');
impact_assert(str_contains($positiveItem['summary'], '资质认定能力项目库'), 'Official facts must include the capability item library');
impact_assert(str_contains($positiveItem['summary'], '证书及标志（CMA）'), 'Official facts must include certificate and CMA mark scope');
impact_assert(str_contains($positiveItem['summary'], '动态管理机制'), 'Official facts must include the dynamic management mechanism');
impact_assert(str_contains($positiveItem['evidence']['raw_text'], '成文日期：2026-04-03'), 'Official issued date must be retained');
impact_assert(str_contains($positiveItem['evidence']['raw_text'], '自2026年6月1日起施行'), 'Official effective date must be retained');
foreach (['更新质量管理体系文件', '确认人员授权范围', '统一数据规则'] as $fabricatedPhrase) {
    impact_assert(
        !str_contains($positiveItem['summary'], $fabricatedPhrase),
        'Official fixture must not inject expected impact conclusion: ' . $fabricatedPhrase
    );
}
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
    'cma_scope_mark' => 'REG-CMA-ONE-LIST-DIRECT-001',
    'qms_documents' => 'REG-QMS-ONE-LIST-INFERENCE-001',
    'personnel_authorization' => 'REG-PER-ONE-LIST-INFERENCE-001',
    'lims_rules' => 'REG-LIMS-ONE-LIST-INFERENCE-001',
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
    $requiredEvidence = implode(' ', $positive['impact_analysis'][$requiredHit]['evidence']);
    impact_assert(str_contains($requiredEvidence, '一单一库'), 'Required hit must quote official input evidence: ' . $requiredHit);
    if ($requiredHit === 'cma_scope_mark') {
        impact_assert(str_contains($requiredEvidence, '公告原文直接要求'), 'CMA rule must identify direct official requirement');
    } else {
        impact_assert(
            str_contains($requiredEvidence, '确定性业务规则推断，非公告原文直接要求'),
            'Cross-category inference must be explicitly labelled: ' . $requiredHit
        );
    }
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
impact_assert_same(0.0, $negative['analysis_confidence'], 'All-no_match analysis must have zero overall confidence');
impact_assert(
    str_contains($negative['analysis_rationale'], '仅按 0 个已命中影响聚合'),
    'Rationale must explain matched-impact-only confidence aggregation'
);
impact_assert(!str_contains(json_encode($negative, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), '不适用'), 'Negative result must not contain not-applicable wording');

$asciiBoundaryNegative = $service->analyze([
    'title' => 'ECMAScript schema 兼容性通知',
    'announcement_number' => 'TECH-2026-7',
    'document_type' => '技术通知',
    'summary' => '本通知仅讨论脚本语言模式定义。',
    'canonical_url' => 'https://example.invalid/cma/schema.html',
    'evidence' => [
        'source_key' => 'cma_schema_feed',
        'entry_url' => 'https://example.invalid/cma/',
        'raw_text' => '本通知仅讨论脚本语言模式定义。',
    ],
], 'company-impact-smoke');
foreach ($asciiBoundaryNegative['impact_analysis'] as $impactKey => $assessment) {
    impact_assert_same('no_match', $assessment['conclusion'], 'Embedded or metadata-only cma must not match: ' . $impactKey);
}
$asciiBoundaryPositive = $service->analyze([
    'title' => '报告标识样例通知',
    'summary' => '报告应依法使用CMA标志。',
    'evidence' => ['raw_text' => '正文明确写明使用CMA标志。'],
], 'company-impact-smoke');
impact_assert(
    in_array('REG-CMA-001', $asciiBoundaryPositive['impact_analysis']['cma_scope_mark']['rule_ids'], true),
    'CMA adjacent to Chinese text must match while ASCII-embedded CMA must not'
);
$listMetadataNegative = $service->analyze([
    'title' => '脚本模式通知',
    'summary' => '本通知仅讨论脚本语言模式定义。',
    'evidence' => [
        [
            'source_key' => 'cma_schema_feed',
            'entry_url' => 'https://example.invalid/CMA/schema.html',
            'raw_text' => '正文仅讨论脚本语言模式定义。',
        ],
    ],
], 'company-impact-smoke');
foreach ($listMetadataNegative['impact_analysis'] as $impactKey => $assessment) {
    impact_assert_same('no_match', $assessment['conclusion'], 'List evidence metadata must not enter rule text: ' . $impactKey);
}
$documentTypePositive = $service->analyze([
    'title' => '动态管理通知',
    'document_type' => '公示公告',
    'summary' => '建立资质认定动态管理机制。',
    'evidence' => ['raw_text' => '正文建立资质认定动态管理机制。'],
], 'company-impact-smoke');
impact_assert(
    in_array('REG-QMS-DOCUMENT-TYPE-001', $documentTypePositive['impact_analysis']['qms_documents']['rule_ids'], true),
    'Configured document type and keyword must both match'
);
$documentTypeNegative = $service->analyze([
    'title' => '动态管理通知',
    'document_type' => '后勤通知',
    'summary' => '建立资质认定动态管理机制。',
    'evidence' => ['raw_text' => '正文建立资质认定动态管理机制。'],
], 'company-impact-smoke');
impact_assert(
    !in_array('REG-QMS-DOCUMENT-TYPE-001', $documentTypeNegative['impact_analysis']['qms_documents']['rule_ids'], true),
    'Wrong document type must not trigger a document-type-scoped rule'
);
foreach (['非公示公告', '公示公告草案'] as $similarDocumentType) {
    $similarDocumentTypeNegative = $service->analyze([
        'title' => '动态管理通知',
        'document_type' => $similarDocumentType,
        'summary' => '建立资质认定动态管理机制。',
        'evidence' => ['raw_text' => '正文建立资质认定动态管理机制。'],
    ], 'company-impact-smoke');
    impact_assert(
        !in_array('REG-QMS-DOCUMENT-TYPE-001', $similarDocumentTypeNegative['impact_analysis']['qms_documents']['rule_ids'], true),
        'Document type rule must require an exact normalized type: ' . $similarDocumentType
    );
}

$stableA = $service->analyze($positiveItem, 'company-impact-smoke');
$stableB = $service->analyze($positiveItem, 'company-impact-smoke');
impact_assert_same(impact_canonical($stableA), impact_canonical($stableB), 'Repeated analysis must be deterministic');
impact_assert(
    preg_match('/^regulatory-impact-v2-[0-9a-f]{12}$/D', $stableA['analysis_rule_version']) === 1,
    'Persisted rule version must include a stable hash of rule definitions'
);
impact_assert(
    str_contains($stableA['analysis_rationale'], '仅按 4 个已命中影响聚合'),
    'Positive rationale must explain matched-impact-only confidence aggregation'
);
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
impact_assert_same(
    1,
    $contextCalls,
    'One service run must cache read-only context by company and analysis date across multiple candidates'
);
$service->analyze($positiveItem, 'company-impact-smoke', '2026-07-15');
impact_assert_same(2, $contextCalls, 'A different analysis date must use a separate context snapshot');

$missingContextService = new RegulatoryImpactService(
    static function (string $companyId): array {
        return [
            'available' => false,
            'reason' => 'offline context fixture password=must-not-be-rendered',
        ];
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
impact_assert(
    !str_contains(json_encode($missingContext, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'must-not-be-rendered'),
    'Unavailable-context reason must not leak into persisted analysis text'
);
$systemFailureService = new RegulatoryImpactService(
    static function (string $companyId): array {
        throw new RuntimeException('context-schema-programming-failure');
    }
);
try {
    $systemFailureService->analyze($positiveItem, 'company-impact-smoke');
    throw new RuntimeException('System context failure must propagate');
} catch (RuntimeException $exception) {
    impact_assert_same(
        'context-schema-programming-failure',
        $exception->getMessage(),
        'System context failure must not be swallowed as unavailable data'
    );
}
$invalidContextService = new RegulatoryImpactService(
    static fn (string $companyId, string $asOf): array => [
        'available' => true,
        'qms_structure_layers' => [],
        'active_employee_count' => -1,
        'active_personnel_authorization_count' => 0,
        'active_equipment_count' => 0,
        'active_equipment_authorization_count' => 0,
    ]
);
try {
    $invalidContextService->analyze($positiveItem, 'company-impact-smoke', '2026-04-23');
    throw new RuntimeException('Invalid context invariant must propagate');
} catch (RuntimeException $exception) {
    impact_assert(
        str_contains($exception->getMessage(), '上下文') && str_contains($exception->getMessage(), '无效'),
        'Invalid context invariant must be reported as a programming/data error'
    );
}

$contextCompanyId = qms_uuid();
$contextEmployeeId = qms_uuid();
$deletedEmployeeId = qms_uuid();
$activeEquipmentId = qms_uuid();
$deletedEquipmentId = qms_uuid();
$decommissionedEquipmentId = qms_uuid();
$contextEmployeeIds = [$contextEmployeeId, $deletedEmployeeId];
$contextEquipmentIds = [$activeEquipmentId, $deletedEquipmentId, $decommissionedEquipmentId];
$contextAppointmentIds = [];
$contextAuthorizationIds = [];
try {
    Db::name('employees')->insertAll([
        [
            'id' => $contextEmployeeId,
            'company_id' => $contextCompanyId,
            'name' => '上下文有效人员',
            'publish' => 1,
            'soft_delete' => 0,
            'created' => '2026-01-01 00:00:00',
            'modified' => '2026-01-01 00:00:00',
        ],
        [
            'id' => $deletedEmployeeId,
            'company_id' => $contextCompanyId,
            'name' => '上下文软删人员',
            'publish' => 1,
            'soft_delete' => 1,
            'created' => '2026-01-01 00:00:00',
            'modified' => '2026-01-01 00:00:00',
        ],
    ]);
    foreach ([
        ['employee_id' => $contextEmployeeId, 'appointed_at' => '2026-01-01', 'valid_until' => '2026-12-31'],
        ['employee_id' => $contextEmployeeId, 'appointed_at' => '2026-01-01', 'valid_until' => '2026-04-22'],
        ['employee_id' => $contextEmployeeId, 'appointed_at' => '2026-04-24', 'valid_until' => '2026-12-31'],
        ['employee_id' => $deletedEmployeeId, 'appointed_at' => '2026-01-01', 'valid_until' => '2026-12-31'],
    ] as $index => $appointmentFixture) {
        $appointmentId = qms_uuid();
        $contextAppointmentIds[] = $appointmentId;
        Db::name('employee_appointments')->insert([
            'id' => $appointmentId,
            'company_id' => $contextCompanyId,
            'employee_id' => $appointmentFixture['employee_id'],
            'appointment_key' => 'impact-context-' . $appointmentId,
            'position_name' => '检测岗位授权 ' . $index,
            'appointed_at' => $appointmentFixture['appointed_at'],
            'valid_until' => $appointmentFixture['valid_until'],
            'status' => 'active',
            'publish' => 1,
            'soft_delete' => 0,
            'created' => '2026-01-01 00:00:00',
            'modified' => '2026-01-01 00:00:00',
        ]);
    }
    Db::name('equipments')->insertAll([
        [
            'id' => $activeEquipmentId,
            'company_id' => $contextCompanyId,
            'equipment_number' => 'CTX-ACTIVE',
            'name' => '上下文有效设备',
            'status' => 'active',
            'publish' => 1,
            'soft_delete' => 0,
            'created' => '2026-01-01 00:00:00',
            'modified' => '2026-01-01 00:00:00',
        ],
        [
            'id' => $deletedEquipmentId,
            'company_id' => $contextCompanyId,
            'equipment_number' => 'CTX-DELETED',
            'name' => '上下文软删设备',
            'status' => 'active',
            'publish' => 1,
            'soft_delete' => 1,
            'created' => '2026-01-01 00:00:00',
            'modified' => '2026-01-01 00:00:00',
        ],
        [
            'id' => $decommissionedEquipmentId,
            'company_id' => $contextCompanyId,
            'equipment_number' => 'CTX-DECOMMISSIONED',
            'name' => '上下文停用设备',
            'status' => 'decommissioned',
            'publish' => 1,
            'soft_delete' => 0,
            'created' => '2026-01-01 00:00:00',
            'modified' => '2026-01-01 00:00:00',
        ],
    ]);
    foreach ([
        ['equipment_id' => $activeEquipmentId, 'employee_id' => $contextEmployeeId, 'authorized_date' => '2026-01-01', 'valid_until' => '2026-12-31'],
        ['equipment_id' => $activeEquipmentId, 'employee_id' => $contextEmployeeId, 'authorized_date' => '2026-01-01', 'valid_until' => '2026-04-22'],
        ['equipment_id' => $activeEquipmentId, 'employee_id' => $contextEmployeeId, 'authorized_date' => '2026-04-24', 'valid_until' => '2026-12-31'],
        ['equipment_id' => $deletedEquipmentId, 'employee_id' => $contextEmployeeId, 'authorized_date' => '2026-01-01', 'valid_until' => '2026-12-31'],
        ['equipment_id' => $activeEquipmentId, 'employee_id' => $deletedEmployeeId, 'authorized_date' => '2026-01-01', 'valid_until' => '2026-12-31'],
    ] as $authorizationFixture) {
        $authorizationId = qms_uuid();
        $contextAuthorizationIds[] = $authorizationId;
        Db::name('equipment_authorizations')->insert([
            'id' => $authorizationId,
            'company_id' => $contextCompanyId,
            'equipment_id' => $authorizationFixture['equipment_id'],
            'employee_id' => $authorizationFixture['employee_id'],
            'authorized_date' => $authorizationFixture['authorized_date'],
            'valid_until' => $authorizationFixture['valid_until'],
            'status' => 'active',
            'publish' => 1,
            'soft_delete' => 0,
            'created' => '2026-01-01 00:00:00',
            'modified' => '2026-01-01 00:00:00',
        ]);
    }

    $realContext = RegulatoryImpactService::readOnlyOrganizationContext($contextCompanyId, '2026-04-23');
    impact_assert_same(true, $realContext['available'], 'Default context provider must report successful read');
    impact_assert_same('2026-04-23', $realContext['as_of'], 'Default context provider must retain analysis date');
    impact_assert_same(1, (int)$realContext['active_employee_count'], 'Soft-deleted employee must be excluded');
    impact_assert_same(1, (int)$realContext['active_personnel_authorization_count'], 'Expired, future and soft-linked appointments must be excluded');
    impact_assert_same(1, (int)$realContext['active_equipment_count'], 'Soft-deleted and decommissioned equipment must be excluded');
    impact_assert_same(1, (int)$realContext['active_equipment_authorization_count'], 'Expired, future and invalid-linked equipment authorizations must be excluded');
} finally {
    if ($contextAuthorizationIds !== []) {
        Db::name('equipment_authorizations')->whereIn('id', $contextAuthorizationIds)->delete();
    }
    if ($contextAppointmentIds !== []) {
        Db::name('employee_appointments')->whereIn('id', $contextAppointmentIds)->delete();
    }
    Db::name('equipments')->whereIn('id', $contextEquipmentIds)->delete();
    Db::name('employees')->whereIn('id', $contextEmployeeIds)->delete();
}

echo "regulatory_impact_smoke passed\n";
