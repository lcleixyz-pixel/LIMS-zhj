<?php
declare(strict_types=1);

require __DIR__ . '/support/qms_responsibility_fixture.php';

use app\service\QmsManualProcedureAlignmentService;
use app\service\QmsManualProcedureTraceService;
use app\service\QmsResponsibilityAlignmentService;
use app\service\QmsResponsibilityCatalogService;
use app\service\QmsResponsibilityDraftService;
use think\facade\Db;

function responsibility_alignment_index_by_id(array $findings): array
{
    $indexed = [];
    foreach ($findings as $finding) {
        $indexed[(string)$finding['finding_id']] = $finding;
    }

    return $indexed;
}

catalog_in_transaction(function (): void {
    $fixture = __DIR__ . '/fixtures/qms_manual_procedure_alignment';
    $loaded = QmsManualProcedureAlignmentService::loadInputs(
        $fixture . '/pilot-spec.json',
        $fixture . '/procedures'
    );
    $trace = QmsManualProcedureTraceService::fromSnapshot($fixture . '/trace-snapshot.json');
    $legacyResult = QmsManualProcedureAlignmentService::check($loaded, $trace);
    $legacyFindings = responsibility_alignment_index_by_id($legacyResult['findings']);

    $draft = QmsResponsibilityCatalogService::createInitialDraft();
    responsibility_assert_throws(
        fn () => QmsResponsibilityAlignmentService::baselineForVersion((string)$draft['id']),
        'A draft is rejected unless preview is explicit'
    );

    $draftPreview = QmsResponsibilityAlignmentService::baselineForVersion((string)$draft['id'], true);
    catalog_assert($draftPreview['version']['status'] === 'draft', 'An explicitly requested draft preview is accepted');
    catalog_assert(strlen((string)$draftPreview['version']['content_hash']) === 64, 'Draft preview records a live content hash');

    $effectiveHash = QmsResponsibilityDraftService::contentHash((string)$draft['id']);
    Db::name('qms_responsibility_chain_versions')
        ->where('id', (string)$draft['id'])
        ->update([
            'status' => 'effective',
            'content_hash' => $effectiveHash,
            'effective_at' => date('Y-m-d H:i:s'),
        ]);

    $baseline = QmsResponsibilityAlignmentService::baselineForVersion((string)$draft['id']);
    catalog_assert($baseline['version']['id'] === (string)$draft['id'], 'Baseline records the version id');
    catalog_assert($baseline['version']['status'] === 'effective', 'Baseline records the effective status');
    catalog_assert($baseline['version']['content_hash'] === $effectiveHash, 'Baseline records the locked version hash');
    catalog_assert(strlen((string)$baseline['version']['baseline_hash']) === 64, 'Baseline records an independently auditable projection hash');
    catalog_assert(count($baseline['activities']) === 3, 'Baseline exposes all three activities');
    catalog_assert(count($baseline['responsibilities']) === 21, 'Baseline exposes all twenty-one source duties');
    catalog_assert($baseline['aliases']['公司总经理']['position_code'] === 'company_general_manager', 'Company GM alias is confirmed');
    catalog_assert($baseline['aliases']['公司总经理']['confirmation_status'] === 'confirmed', 'Company GM alias confirmation is retained');
    catalog_assert($baseline['aliases']['最高管理者']['position_code'] === 'lab_director', 'Highest manager alias is confirmed');
    catalog_assert($baseline['aliases']['经理']['confirmation_status'] === 'review_required', 'Bare manager remains ambiguous');

    $inputs = QmsResponsibilityAlignmentService::injectBaseline($loaded, $baseline);
    catalog_assert(
        $inputs['requirements'][0] === $loaded['requirements'][0]
        && $inputs['requirements'][1] === $loaded['requirements'][1]
        && $inputs['requirements'][2] === $loaded['requirements'][2]
        && $inputs['requirements'][3] === $loaded['requirements'][3],
        'Y14/Y15/Y17/Y18 inputs are byte-for-byte unchanged'
    );
    $requirementsById = [];
    foreach ($inputs['requirements'] as $requirement) {
        $requirementsById[(string)$requirement['id']] = $requirement;
    }
    catalog_assert($requirementsById['Y13-CX20']['expected']['role_code'] === 'quality_manager', 'Internal-audit expected role comes from the chain');
    catalog_assert($requirementsById['Y13-CX21']['expected']['role_code'] === 'lab_director', 'Management-review expected role comes from the chain');
    catalog_assert($requirementsById['Y13-CX32']['expected']['role_code'] === 'lab_director', 'General-risk approval comes from the chain');
    catalog_assert(
        $requirementsById['Y13-CX21']['expected']['source_step_code'] === 'mr_preside_approve'
        && $requirementsById['Y13-CX21']['expected']['source_responsibility_id'] !== '',
        'Injected expectations retain their source responsibility item'
    );

    $result = QmsManualProcedureAlignmentService::check($inputs, $trace);
    $findings = responsibility_alignment_index_by_id($result['findings']);
    catalog_assert($findings['Y13-CX20']['status'] === 'conflict', 'Internal audit role conflict remains explicit');
    catalog_assert($findings['Y13-CX21']['status'] === 'conflict', 'Company GM differs from lab director and becomes conflict');
    catalog_assert($findings['Y13-CX32']['status'] === 'review_required', 'Bare manager still requires review');
    catalog_assert(
        in_array('company_general_manager', $findings['Y13-CX21']['observed']['position_codes'], true),
        'Confirmed company GM alias participates in conflict comparison'
    );
    catalog_assert(
        !in_array('公司总经理', $findings['Y13-CX21']['observed']['unconfirmed_aliases'], true),
        'Confirmed aliases are not mislabeled as unconfirmed in catalog mode'
    );
    catalog_assert(
        in_array('经理', $findings['Y13-CX32']['observed']['review_required_aliases'], true),
        'Review-required alias remains visible for human review'
    );
    catalog_assert(
        $result['responsibility_chain_version'] === $baseline['version'],
        'Alignment result records responsibility-chain version metadata'
    );

    $crossActivityBaseline = $baseline;
    $crossActivityDuplicate = $requirementsById['Y13-CX20']['expected']['source_responsibility_id'];
    foreach ($crossActivityBaseline['responsibilities'] as $responsibility) {
        if ((string)$responsibility['responsibility_id'] !== $crossActivityDuplicate) {
            continue;
        }
        $responsibility['responsibility_id'] = qms_uuid();
        $responsibility['activity_code'] = 'risk_management';
        $responsibility['position_code'] = 'technical_manager';
        $responsibility['position_name'] = '技术负责人';
        $crossActivityBaseline['responsibilities'][] = $responsibility;
        break;
    }
    $crossActivityInputs = QmsResponsibilityAlignmentService::injectBaseline($loaded, $crossActivityBaseline);
    $crossActivityRequirements = [];
    foreach ($crossActivityInputs['requirements'] as $requirement) {
        $crossActivityRequirements[(string)$requirement['id']] = $requirement;
    }
    catalog_assert(
        $crossActivityRequirements['Y13-CX20']['expected']['role_code'] === 'quality_manager',
        'The same step code in another activity cannot overwrite the intended source duty'
    );

    $duplicateBaseline = $baseline;
    foreach ($duplicateBaseline['responsibilities'] as $responsibility) {
        if ((string)$responsibility['responsibility_id'] !== $crossActivityDuplicate) {
            continue;
        }
        $responsibility['responsibility_id'] = qms_uuid();
        $duplicateBaseline['responsibilities'][] = $responsibility;
        break;
    }
    responsibility_assert_throws(
        fn () => QmsResponsibilityAlignmentService::injectBaseline($loaded, $duplicateBaseline),
        'Duplicate activity and step mappings fail closed'
    );

    $missingBaseline = $baseline;
    $missingBaseline['responsibilities'] = array_values(array_filter(
        $missingBaseline['responsibilities'],
        static fn (array $responsibility): bool => (string)$responsibility['responsibility_id'] !== $crossActivityDuplicate
    ));
    responsibility_assert_throws(
        fn () => QmsResponsibilityAlignmentService::injectBaseline($loaded, $missingBaseline),
        'Missing activity and step mappings fail closed'
    );

    $wrongChainBaseline = $baseline;
    $wrongChainBaseline['version']['chain_code'] = 'other_governance';
    responsibility_assert_throws(
        fn () => QmsResponsibilityAlignmentService::injectBaseline($loaded, $wrongChainBaseline),
        'Only a core-governance baseline can be injected into the pilot'
    );

    $qualitySupervisor = $inputs;
    $qualitySupervisor['procedures']['XZTC/CX-20-2022']['text'] = str_replace(
        '办公室主任负责制定年度内部审核计划，质量负责人负责审核年度内部审核计划。',
        '质量主管负责制定年度内部审核计划。',
        (string)$qualitySupervisor['procedures']['XZTC/CX-20-2022']['text']
    );
    $qualityFindings = responsibility_alignment_index_by_id(
        QmsManualProcedureAlignmentService::check($qualitySupervisor, $trace)['findings']
    );
    catalog_assert($qualityFindings['Y13-CX20']['status'] === 'consistent', 'Confirmed quality-supervisor alias is consistent');

    $technicalSupervisor = $inputs;
    $technicalSupervisor['procedures']['XZTC/CX-20-2022']['text'] = str_replace(
        '办公室主任负责制定年度内部审核计划，质量负责人负责审核年度内部审核计划。',
        '技术主管负责制定年度内部审核计划。',
        (string)$technicalSupervisor['procedures']['XZTC/CX-20-2022']['text']
    );
    $technicalFindings = responsibility_alignment_index_by_id(
        QmsManualProcedureAlignmentService::check($technicalSupervisor, $trace)['findings']
    );
    catalog_assert($technicalFindings['Y13-CX20']['status'] === 'conflict', 'Confirmed technical-supervisor alias participates in conflict detection');

    $bareManager = $inputs;
    $bareManager['procedures']['XZTC/CX-20-2022']['text'] = str_replace(
        '办公室主任负责制定年度内部审核计划，质量负责人负责审核年度内部审核计划。',
        '经理负责制定年度内部审核计划。',
        (string)$bareManager['procedures']['XZTC/CX-20-2022']['text']
    );
    $bareManagerFindings = responsibility_alignment_index_by_id(
        QmsManualProcedureAlignmentService::check($bareManager, $trace)['findings']
    );
    catalog_assert($bareManagerFindings['Y13-CX20']['status'] === 'review_required', 'Review-required manager alias remains a human-review finding');

    foreach (['Y14', 'Y15', 'Y17', 'Y18'] as $findingId) {
        catalog_assert(
            $findings[$findingId]['status'] === $legacyFindings[$findingId]['status'],
            $findingId . ' status is unchanged by responsibility baseline injection'
        );
    }

    $legacyAgain = QmsManualProcedureAlignmentService::check($loaded, $trace);
    catalog_assert(
        $legacyAgain['findings'] === $legacyResult['findings'],
        'Inputs without role_catalog preserve the exact legacy raw-name result'
    );

    Db::name('qms_position_aliases')
        ->where('company_id', catalog_company_id())
        ->where('alias', '质量主管')
        ->where('source_scope', 'position_catalog')
        ->where('site_scope_key', '*')
        ->update(['confirmation_status' => 'review_required']);
    $aliasChangedBaseline = QmsResponsibilityAlignmentService::baselineForVersion((string)$draft['id']);
    catalog_assert(
        $aliasChangedBaseline['version']['content_hash'] === $baseline['version']['content_hash']
        && $aliasChangedBaseline['version']['baseline_hash'] !== $baseline['version']['baseline_hash'],
        'Alias confirmation drift changes the independent baseline hash even when the locked content hash is unchanged'
    );
    Db::name('qms_position_aliases')
        ->where('company_id', catalog_company_id())
        ->where('alias', '质量主管')
        ->where('source_scope', 'position_catalog')
        ->where('site_scope_key', '*')
        ->update(['confirmation_status' => 'confirmed']);

    Db::name('qms_activity_responsibilities')
        ->where('id', (string)$requirementsById['Y13-CX20']['expected']['source_responsibility_id'])
        ->update(['publish' => 0]);
    responsibility_assert_throws(
        fn () => QmsResponsibilityAlignmentService::baselineForVersion((string)$draft['id']),
        'An unpublished duty cannot silently disappear from an effective baseline'
    );
    Db::name('qms_activity_responsibilities')
        ->where('id', (string)$requirementsById['Y13-CX20']['expected']['source_responsibility_id'])
        ->update(['publish' => 1]);

    Db::name('qms_responsibility_chain_versions')
        ->where('id', (string)$draft['id'])
        ->update(['chain_code' => 'other_governance']);
    responsibility_assert_throws(
        fn () => QmsResponsibilityAlignmentService::baselineForVersion((string)$draft['id']),
        'Only the core governance responsibility chain can drive the pilot mapping'
    );
    Db::name('qms_responsibility_chain_versions')
        ->where('id', (string)$draft['id'])
        ->update(['chain_code' => 'core_governance']);

    Db::name('qms_activity_responsibilities')
        ->where('id', (string)$requirementsById['Y13-CX20']['expected']['source_responsibility_id'])
        ->update(['duty_text' => '未经换版签批的篡改内容']);
    responsibility_assert_throws(
        fn () => QmsResponsibilityAlignmentService::baselineForVersion((string)$draft['id']),
        'An effective baseline rejects content that no longer matches its locked hash'
    );
});

echo "qms_responsibility_alignment_smoke passed\n";
