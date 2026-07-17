<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$document = (string)file_get_contents($root . '/app/controller/Document.php');
$authorization = (string)file_get_contents($root . '/app/service/ActionAuthorizationService.php');
$evidence = (string)file_get_contents($root . '/app/service/ExternalEvidenceReferenceService.php');
$migration = (string)file_get_contents($root . '/database/migrations/20260717_gr14_controlled_trial.sql');

$checks = [
    'DES01' => str_contains($document, '$data[\'site_id\'] = (string)$document->site_id'),
    'DES02' => str_contains($authorization, 'canAddExternalEvidence')
        && str_contains($authorization, 'externalEvidenceSubjectRecord'),
    'DES03' => str_contains($authorization, "'externalevidencereference.list'")
        && str_contains($authorization, "'externalevidencereference.add'"),
    'DES04' => str_contains($evidence, "where('company_id'")
        && str_contains($evidence, 'parse_url')
        && str_contains($evidence, '只读链接不得包含账号凭据'),
    'DES05' => str_contains($evidence, '只读链接必须使用 HTTPS')
        && str_contains($evidence, '只读链接不得包含片段')
        && str_contains($evidence, 'SAFE_QUERY_KEYS')
        && str_contains($evidence, '只读链接仅允许受控查询参数')
        && str_contains($evidence, '只读链接不得包含高熵临时凭据'),
    'DES06' => str_contains($migration, 'qms_gr14_validate_external_evidence_schema')
        && str_contains($migration, 'external_evidence_references 表结构不完整，停止迁移'),
    'DES07' => str_contains($evidence, "where('company_id', (string)Config::get('qms.company_id'))")
        && str_contains($evidence, 'SAFE_QUERY_KEYS')
        && str_contains($evidence, '只读链接路径疑似包含临时凭据'),
    'DES08' => str_contains($evidence, 'SENSITIVE_PATH_SEGMENTS')
        && str_contains($evidence, 'CREDENTIAL_VALUE_PATTERNS')
        && str_contains($evidence, '只读链接参数值疑似包含临时凭据'),
];

$failed = false;
foreach ($checks as $id => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $id . "\n";
    $failed = $failed || !$passed;
}
exit($failed ? 1 : 0);
