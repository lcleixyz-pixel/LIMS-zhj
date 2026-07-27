<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\service\GovernedTrialResolvedManifestService;
use app\service\QmsGovernanceVersionResolverService;

function governance_version_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$structuredRows = [
    [
        'id' => 'structured-gov',
        'document_id' => 'doc-gov',
        'document_role' => 'procedure',
        'doc_number' => 'SIM-GOV02-XZTC/CX-03-02-2022',
        'title' => '标准物质管理程序',
        'version' => GovernedTrialResolvedManifestService::VERSION,
        'status' => 'draft',
        'soft_delete' => 0,
    ],
    [
        'id' => 'structured-old',
        'document_id' => 'doc-a2',
        'document_role' => 'procedure',
        'doc_number' => 'SIM-GOV02-XZTC/CX-03-02-2022',
        'title' => '标准物质管理程序',
        'version' => 'A/2',
        'status' => 'published',
        'soft_delete' => 0,
    ],
    [
        'id' => 'structured-conflict-a',
        'document_id' => 'doc-conflict-a',
        'document_role' => 'procedure',
        'doc_number' => 'SIM-GOV02-XZTC/CX-09-2022',
        'title' => '合同评审程序',
        'version' => GovernedTrialResolvedManifestService::VERSION,
        'status' => 'draft',
        'soft_delete' => 0,
    ],
    [
        'id' => 'structured-conflict-b',
        'document_id' => 'doc-conflict-b',
        'document_role' => 'procedure',
        'doc_number' => 'SIM-GOV02-XZTC/CX-09-2022',
        'title' => '合同评审程序',
        'version' => GovernedTrialResolvedManifestService::VERSION,
        'status' => 'draft',
        'soft_delete' => 0,
    ],
    [
        'id' => 'structured-reference',
        'document_id' => 'doc-reference',
        'document_role' => 'procedure',
        'doc_number' => 'REF-2025-PROCEDURES',
        'title' => '参考程序',
        'version' => GovernedTrialResolvedManifestService::VERSION,
        'status' => 'draft',
        'soft_delete' => 0,
    ],
];

$resolved = QmsGovernanceVersionResolverService::resolveCandidateRecords($structuredRows);
$cx0302 = $resolved['by_doc_number']['SIM-GOV02-XZTC/CX-03-02-2022'] ?? [];
$cx09 = $resolved['by_doc_number']['SIM-GOV02-XZTC/CX-09-2022'] ?? [];

governance_version_assert(
    ($resolved['candidate_version'] ?? '') === GovernedTrialResolvedManifestService::VERSION,
    '候选版本必须复用治理解析稿版本常量'
);
governance_version_assert(
    ($cx0302['state'] ?? '') === 'current_candidate'
    && ($cx0302['structured_id'] ?? '') === 'structured-gov'
    && (int)($cx0302['candidate_count'] ?? 0) === 1,
    '单一 GOV-TRIAL/0.2 程序应解析为当前电子治理候选'
);
governance_version_assert(
    ($cx09['state'] ?? '') === 'candidate_conflict'
    && (int)($cx09['candidate_count'] ?? 0) === 2
    && ($cx09['structured_id'] ?? '') === '',
    '同一编号存在多个电子候选时必须失败关闭，不得静默挑选'
);
governance_version_assert(
    !isset($resolved['by_doc_number']['REF-2025-PROCEDURES']),
    '参考程序不得进入本批次电子候选'
);

$documents = [
    [
        'id' => 'doc-gov',
        'doc_number' => 'SIM-GOV02-XZTC/CX-03-02-2022',
        'version' => GovernedTrialResolvedManifestService::VERSION,
    ],
    [
        'id' => 'doc-a2',
        'doc_number' => 'SIM-GOV02-XZTC/CX-03-02-2022',
        'version' => 'A/2',
    ],
    [
        'id' => 'doc-conflict-a',
        'doc_number' => 'SIM-GOV02-XZTC/CX-09-2022',
        'version' => GovernedTrialResolvedManifestService::VERSION,
    ],
    [
        'id' => 'doc-standard',
        'doc_number' => 'XZTC/CX-08-2022',
        'version' => '2022',
    ],
];

$roles = QmsGovernanceVersionResolverService::classifyControlledDocuments($documents, $resolved);
governance_version_assert(
    ($roles['doc-gov']['role'] ?? '') === 'current_candidate'
    && ($roles['doc-gov']['label'] ?? '') === '当前电子治理候选',
    '候选受控文件应明确标记为当前电子治理候选'
);
governance_version_assert(
    ($roles['doc-a2']['role'] ?? '') === 'source_version'
    && ($roles['doc-a2']['label'] ?? '') === '纸质现用来源',
    '同一编号的其他版本应标记为纸质现用来源'
);
governance_version_assert(
    ($roles['doc-conflict-a']['role'] ?? '') === 'candidate_conflict',
    '候选冲突必须传递到受控文件列表'
);
governance_version_assert(
    ($roles['doc-standard']['role'] ?? '') === 'standard'
    && ($roles['doc-standard']['label'] ?? '') === '',
    '本批次外文件应保持标准展示'
);

echo "qms_governance_version_resolver_smoke passed\n";
