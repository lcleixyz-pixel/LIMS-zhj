<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/app/common.php';

use app\service\QmsGovernanceQueueService;
use app\service\QmsGovernanceVersionResolverService;
use think\facade\Db;

(new think\App())->initialize();

function governance_queue_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function governance_queue_runtime_counts(): array
{
    return [
        'structures' => Db::name('qms_structured_documents')
            ->where('soft_delete', 0)
            ->count(),
        'blocks' => Db::name('qms_document_blocks')
            ->where('soft_delete', 0)
            ->count(),
        'links' => Db::name('qms_document_block_links')
            ->where('soft_delete', 0)
            ->count(),
        'documents' => Db::name('documents')
            ->where('soft_delete', 0)
            ->count(),
    ];
}

$before = governance_queue_runtime_counts();
$queue = QmsGovernanceQueueService::listing();
$after = governance_queue_runtime_counts();

governance_queue_runtime_assert($before === $after, '治理队列读取不得改变数据库计数');
governance_queue_runtime_assert(
    (int)($queue['scope']['total'] ?? 0) === 37
    && count((array)($queue['all_rows'] ?? [])) === 37,
    '当前批次应稳定解析为 37 份程序文件'
);
governance_queue_runtime_assert(
    (int)($queue['summary']['aligned'] ?? 0) === 0
    && (int)($queue['summary']['suspected_mismatch'] ?? 0) === 18
    && (int)($queue['summary']['missing_primary'] ?? 0) === 19
    && (int)($queue['summary']['version_conflict'] ?? 0) === 0,
    '原因分类后治理队列应如实计入 4 份历史混装程序'
);

$next = QmsGovernanceQueueService::nextUnresolved('', '');
governance_queue_runtime_assert(
    ($next['next_eligible'] ?? false) === true
    && (string)($next['structured_id'] ?? '') !== ''
    && (string)($next['document_status'] ?? '') !== 'obsolete',
    '下一份未完成必须返回可办理且未废止的电子治理候选'
);

$resolution = QmsGovernanceVersionResolverService::candidateResolution();
$cx0302 = (array)(
    $resolution['by_doc_number']['SIM-GOV02-XZTC/CX-03-02-2022'] ?? []
);
governance_queue_runtime_assert(
    (string)($cx0302['state'] ?? '') === 'current_candidate'
    && (string)($cx0302['document_id'] ?? '') !== '',
    'CX-03-02 应解析出唯一电子治理候选'
);

$controlledDocuments = Db::name('documents')
    ->where('doc_number', 'SIM-GOV02-XZTC/CX-03-02-2022')
    ->where('soft_delete', 0)
    ->select()
    ->toArray();
$roles = QmsGovernanceVersionResolverService::classifyControlledDocuments(
    $controlledDocuments,
    $resolution
);
governance_queue_runtime_assert(
    (string)($roles[(string)$cx0302['document_id']]['role'] ?? '') === 'current_candidate',
    'CX-03-02 的治理受控文件应标记为当前电子治理候选'
);
$sourceVersions = array_filter(
    $roles,
    static fn(array $role): bool => (string)($role['role'] ?? '') === 'source_version'
);
governance_queue_runtime_assert(
    $sourceVersions !== [],
    'CX-03-02 的其他受控版本应保留纸质现用来源标识'
);

echo "qms_governance_queue_runtime_smoke passed\n";
