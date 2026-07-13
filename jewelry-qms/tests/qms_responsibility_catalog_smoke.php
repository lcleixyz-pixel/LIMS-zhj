<?php
declare(strict_types=1);

require __DIR__ . '/support/qms_responsibility_fixture.php';

use app\service\QmsPositionAliasService;
use app\service\QmsResponsibilityCatalogService;
use app\service\QmsDocumentStructureService;
use app\model\QmsActivityResponsibility;
use app\model\QmsResponsibilityActivity;
use think\facade\Db;

catalog_in_transaction(function (): void {
    $position = Db::name('qms_positions')->where('code', 'quality_manager')->find();
    $positionId = (string)($position['id'] ?? qms_uuid());
    $customFields = [
        'company_id' => catalog_company_id(),
        'code' => 'quality_manager',
        'name' => '人工维护的质量岗',
        'source' => 'user_governed',
        'review_status' => 'published',
        'publish' => 1,
        'soft_delete' => 0,
    ];
    if ($position) {
        Db::name('qms_positions')->where('id', $positionId)->update($customFields);
    } else {
        Db::name('qms_positions')->insert(array_merge($customFields, [
            'id' => $positionId,
            'created' => date('Y-m-d H:i:s'),
            'modified' => date('Y-m-d H:i:s'),
        ]));
    }

    $positions = QmsPositionAliasService::seedCatalog();
    $after = Db::name('qms_positions')->where('id', $positionId)->find();
    catalog_assert(($positions['quality_manager']['id'] ?? '') === $positionId, 'Custom current-company position id is reused');
    foreach ($customFields as $field => $expected) {
        catalog_assert(($after[$field] ?? null) === $expected, 'Custom position field is preserved: ' . $field);
    }

    $version = QmsResponsibilityCatalogService::createInitialDraft();
    catalog_assert(
        (int)Db::name('qms_activity_responsibilities')->alias('r')
            ->join('qms_responsibility_activities a', 'a.id = r.activity_id')
            ->where('a.chain_version_id', (string)$version['id'])
            ->where('r.fixed_position_id', $positionId)
            ->where('r.soft_delete', 0)
            ->count() > 0,
        'Active custom position id is reused by fixed duties'
    );
});

foreach ([
    'soft_deleted' => ['soft_delete' => 1],
    'unpublished' => ['publish' => 0],
    'obsolete' => ['review_status' => 'obsolete'],
] as $caseName => $invalidState) {
    catalog_in_transaction(function () use ($caseName, $invalidState): void {
        $position = Db::name('qms_positions')->where('code', 'quality_manager')->find();
        $positionId = (string)($position['id'] ?? qms_uuid());
        $invalidFields = array_merge([
            'company_id' => catalog_company_id(),
            'code' => 'quality_manager',
            'name' => '人工维护但无效的质量岗',
            'source' => 'user_governed',
            'review_status' => 'published',
            'publish' => 1,
            'soft_delete' => 0,
        ], $invalidState);
        if ($position) {
            Db::name('qms_positions')->where('id', $positionId)->update($invalidFields);
        } else {
            Db::name('qms_positions')->insert(array_merge($invalidFields, [
                'id' => $positionId,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ]));
        }

        $seedBlocked = false;
        try {
            QmsPositionAliasService::seedCatalog();
        } catch (\DomainException $exception) {
            $seedBlocked = str_contains($exception->getMessage(), 'quality_manager');
        }
        catalog_assert($seedBlocked, 'Invalid custom position blocks catalog seed: ' . $caseName);

        $createBlocked = false;
        try {
            QmsResponsibilityCatalogService::createInitialDraft();
        } catch (\DomainException $exception) {
            $createBlocked = str_contains($exception->getMessage(), 'quality_manager');
        }
        catalog_assert($createBlocked, 'Invalid custom position blocks responsibility chain creation: ' . $caseName);

        $after = Db::name('qms_positions')->where('id', $positionId)->find();
        foreach ($invalidFields as $field => $expected) {
            catalog_assert(($after[$field] ?? null) === $expected, 'Invalid custom position field is preserved: ' . $caseName . '/' . $field);
        }
        catalog_assert(
            (int)Db::name('qms_responsibility_chain_versions')
                ->where('company_id', catalog_company_id())
                ->where('chain_code', 'core_governance')
                ->where('soft_delete', 0)
                ->count() === 0,
            'Invalid custom position does not create a responsibility chain: ' . $caseName
        );
    });
}

catalog_in_transaction(function (): void {
    $otherCompanyId = 'ffffffff-ffff-ffff-ffff-ffffffffffff';
    Db::name('companies')->insert([
        'id' => $otherCompanyId,
        'name' => '跨公司冲突测试机构',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => date('Y-m-d H:i:s'),
        'modified' => date('Y-m-d H:i:s'),
    ]);
    $position = Db::name('qms_positions')->where('code', 'supervisor')->find();
    $positionId = (string)($position['id'] ?? qms_uuid());
    $foreignFields = [
        'company_id' => $otherCompanyId,
        'code' => 'supervisor',
        'name' => '其他公司监督员',
        'source' => 'foreign_governance',
        'review_status' => 'published',
        'publish' => 1,
        'soft_delete' => 0,
    ];
    if ($position) {
        Db::name('qms_positions')->where('id', $positionId)->update($foreignFields);
    } else {
        Db::name('qms_positions')->insert(array_merge($foreignFields, [
            'id' => $positionId,
            'created' => date('Y-m-d H:i:s'),
            'modified' => date('Y-m-d H:i:s'),
        ]));
    }

    $blocked = false;
    try {
        QmsPositionAliasService::seedCatalog();
    } catch (\DomainException $exception) {
        $blocked = true;
    }
    catalog_assert($blocked, 'A position code owned by another company fails closed');
    $after = Db::name('qms_positions')->where('id', $positionId)->find();
    catalog_assert(($after['company_id'] ?? '') === $otherCompanyId, 'Foreign position ownership is never transferred');
    catalog_assert(($after['name'] ?? '') === '其他公司监督员', 'Foreign position name is never overwritten');
});

catalog_in_transaction(function (): void {
    $definitions = QmsPositionAliasService::defaultDefinitions();
    catalog_assert(count($definitions) === 13, 'Twelve legacy positions plus company GM are defined');
    catalog_assert(
        ($definitions['company_general_manager']['aliases']['经理'] ?? '') === 'review_required',
        'Bare manager is never auto-confirmed'
    );

    $version = QmsResponsibilityCatalogService::createInitialDraft();
    catalog_assert($version['chain_code'] === 'core_governance', 'Core governance chain is created');
    catalog_assert($version['version_no'] === 1, 'Initial version is one');
    catalog_assert($version['status'] === 'draft', 'Initial version is draft');
    catalog_assert(count($version['activities']) === 3, 'Three activities are seeded');
    catalog_assert(array_sum(array_column($version['activities'], 'responsibility_count')) === 21, 'Twenty-one duties are seeded');

    $activityCounts = [];
    foreach ($version['activities'] as $activity) {
        $activityCounts[(string)$activity['activity_code']] = (int)$activity['responsibility_count'];
    }
    catalog_assert($activityCounts === [
        'internal_audit' => 7,
        'management_review' => 6,
        'risk_management' => 8,
    ], 'Activity duty counts are exactly 7, 6 and 8');

    $duties = Db::name('qms_activity_responsibilities')
        ->alias('r')
        ->join('qms_responsibility_activities a', 'a.id = r.activity_id')
        ->where('a.chain_version_id', (string)$version['id'])
        ->where('a.soft_delete', 0)
        ->where('r.soft_delete', 0)
        ->field('a.activity_code,r.*')
        ->order('a.sort_order,r.sort_order')
        ->select()
        ->toArray();
    catalog_assert(count($duties) === 21, 'Database contains exactly twenty-one active duties');

    $byStep = [];
    foreach ($duties as $duty) {
        $byStep[(string)$duty['step_code']] = $duty;
        $eligibility = json_decode((string)($duty['eligibility_rule'] ?? ''), true) ?: [];
        if ((string)$duty['slot_kind'] === 'fixed_position') {
            catalog_assert((string)$duty['assignment_mode'] === 'named_person', 'Fixed positions use named-person assignment');
            catalog_assert((string)$duty['fixed_position_id'] !== '', 'Fixed positions resolve to qms_positions.id');
            catalog_assert(($eligibility['evidence_required'] ?? false) === true, 'Fixed positions require evidence');
        } else {
            catalog_assert((string)$duty['assignment_mode'] !== 'named_person', 'Runtime slots do not use named-person assignment');
            if ((string)$duty['slot_kind'] === 'activity_role') {
                catalog_assert((string)$duty['activity_role_code'] !== '', 'Activity roles keep their role code');
                catalog_assert((string)($duty['dynamic_owner_code'] ?? '') === '', 'Activity roles do not masquerade as dynamic owners');
            }
            if ((string)$duty['slot_kind'] === 'dynamic_owner') {
                catalog_assert((string)$duty['dynamic_owner_code'] !== '', 'Dynamic owners keep their owner code');
                catalog_assert((string)($duty['activity_role_code'] ?? '') === '', 'Dynamic owners do not masquerade as activity roles');
            }
        }
        catalog_assert((int)$duty['required'] === 1, 'Initial governance duties are required');
        catalog_assert((json_decode((string)($duty['source_refs'] ?? ''), true) ?: []) !== [], 'Every duty keeps source references');
    }

    catalog_assert(($byStep['ia_audit_execution']['slot_kind'] ?? '') === 'activity_role', 'Internal auditors are an activity role');
    catalog_assert(($byStep['ia_audit_execution']['assignment_mode'] ?? '') === 'activity_instance', 'Internal auditors are assigned per activity');
    catalog_assert(
        in_array('no_self_audit', json_decode((string)$byStep['ia_audit_execution']['rule_codes'], true) ?: [], true),
        'Internal audit duty preserves no-self-audit rule'
    );
    catalog_assert(($byStep['ia_correction']['slot_kind'] ?? '') === 'dynamic_owner', 'Audited owner is derived dynamically');
    catalog_assert(($byStep['ia_correction']['assignment_mode'] ?? '') === 'derived_from_scope', 'Audited owner derives from scope');
    catalog_assert(($byStep['risk_verify']['slot_kind'] ?? '') === 'activity_role', 'Risk verifier is an activity role');
    catalog_assert(
        in_array('separate_executor_verifier', json_decode((string)$byStep['risk_verify']['rule_codes'], true) ?: [], true),
        'Risk verification preserves separation rule'
    );
    catalog_assert((string)($byStep['risk_major_approval']['fixed_position_id'] ?? '') !== '', 'Major risk approval resolves company GM position');

    $activityModel = QmsResponsibilityActivity::where('id', (string)$version['activities'][0]['id'])->find();
    catalog_assert(is_array($activityModel?->source_refs), 'Activity model casts source_refs JSON');
    $dutyModel = QmsActivityResponsibility::where('id', (string)$byStep['ia_audit_execution']['id'])->find();
    catalog_assert(is_array($dutyModel?->rule_codes), 'Duty model casts rule_codes JSON');

    $sameVersion = QmsResponsibilityCatalogService::createInitialDraft();
    catalog_assert($sameVersion['id'] === $version['id'], 'Initial draft is idempotent');

    catalog_assert(
        (int)Db::name('qms_responsibility_chain_versions')->where('chain_code', 'core_governance')->where('soft_delete', 0)->count() === 1,
        'Idempotent draft creation does not duplicate versions'
    );
    catalog_assert(
        (int)Db::name('qms_responsibility_activities')->where('chain_version_id', (string)$version['id'])->where('soft_delete', 0)->count() === 3,
        'Idempotent draft creation does not duplicate activities'
    );
    catalog_assert(
        (int)Db::name('qms_activity_responsibilities')->alias('r')
            ->join('qms_responsibility_activities a', 'a.id = r.activity_id')
            ->where('a.chain_version_id', (string)$version['id'])
            ->where('r.soft_delete', 0)
            ->count() === 21,
        'Idempotent draft creation does not duplicate duties'
    );

    $aliases = QmsPositionAliasService::aliasCatalog();
    catalog_assert($aliases['最高管理者']['position_code'] === 'lab_director', 'Highest manager maps to lab director');
    catalog_assert($aliases['最高管理者']['confirmation_status'] === 'confirmed', 'Highest manager alias is confirmed');
    catalog_assert($aliases['公司总经理']['position_code'] === 'company_general_manager', 'Company GM is distinct');
    catalog_assert($aliases['公司总经理']['position_name'] === '公司总经理', 'Company GM keeps its canonical name');
    catalog_assert($aliases['公司总经理']['confirmation_status'] === 'confirmed', 'Company GM alias is confirmed');
    catalog_assert($aliases['经理']['confirmation_status'] === 'review_required', 'Bare manager requires review');
    catalog_assert(
        (int)Db::name('qms_position_aliases')->where('company_id', catalog_company_id())->where('source_scope', 'position_catalog')->where('site_scope_key', '*')->where('soft_delete', 0)->count() === 28,
        'Alias rows are complete and not duplicated'
    );

    $gmMentions = QmsDocumentStructureService::positionMentionsForMarkdown('由公司总经理批准。');
    catalog_assert(($gmMentions[0]['position_code'] ?? '') === 'company_general_manager', 'Legacy structure parsing recognizes confirmed company GM alias');
    $bareManagerMentions = QmsDocumentStructureService::positionMentionsForMarkdown('由经理批准。');
    catalog_assert($bareManagerMentions === [], 'Legacy structure parsing does not auto-confirm bare manager');

    Db::name('qms_responsibility_chain_versions')->where('id', (string)$version['id'])->update(['status' => 'effective']);
    $effectiveV1 = QmsResponsibilityCatalogService::createInitialDraft();
    catalog_assert($effectiveV1['id'] === $version['id'], 'An effective version one remains idempotent');
    catalog_assert($effectiveV1['status'] === 'effective', 'Existing effective version one is returned as-is');

    Db::name('qms_responsibility_chain_versions')->where('id', (string)$version['id'])->update(['soft_delete' => 1]);
    Db::name('qms_responsibility_chain_versions')->insert([
        'id' => qms_uuid(),
        'company_id' => catalog_company_id(),
        'chain_code' => 'core_governance',
        'version_no' => 2,
        'status' => 'effective',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => date('Y-m-d H:i:s'),
        'modified' => date('Y-m-d H:i:s'),
    ]);
    $blocked = false;
    try {
        QmsResponsibilityCatalogService::createInitialDraft();
    } catch (\DomainException $exception) {
        $blocked = true;
    }
    catalog_assert($blocked, 'Another effective version blocks recreating missing version one');
});

echo "qms_responsibility_catalog_smoke passed\n";
