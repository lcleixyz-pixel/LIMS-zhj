<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/app/common.php';

use app\service\QmsDocumentStructureService;
use think\facade\Db;

(new think\App())->initialize();

function trace_review_search_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function trace_review_candidate_count(array $rows): int
{
    return count(array_filter(
        $rows,
        static fn(array $row): bool =>
            (bool)($row['is_candidate'] ?? false)
    ));
}

$structured = Db::name('qms_structured_documents')
    ->where('doc_number', 'SIM-GOV02-XZTC/CX-03-02-2022')
    ->where('version', 'GOV-TRIAL/0.2')
    ->where('soft_delete', 0)
    ->find();
trace_review_search_assert(
    is_array($structured),
    '8021 缺少 CX-03-02 GOV-TRIAL/0.2 结构化文件'
);

$blocks = Db::name('qms_document_blocks')
    ->where('structured_document_id', (string)$structured['id'])
    ->whereIn('block_type', ['purpose', 'record_requirement'])
    ->where('soft_delete', 0)
    ->order('sort_order', 'asc')
    ->select()
    ->toArray();
$blocksByType = [];
foreach ($blocks as $block) {
    $blocksByType[(string)$block['block_type']] = $block;
}
trace_review_search_assert(
    isset($blocksByType['purpose'], $blocksByType['record_requirement']),
    'CX-03-02 应同时具有目的块和记录要求块'
);

$beforeLinks = Db::name('qms_document_block_links')
    ->where('soft_delete', 0)
    ->count();
$invalidHistoricalLinks = Db::name('qms_document_block_links')
    ->alias('link')
    ->join('qms_clauses clause', 'clause.id = link.clause_id')
    ->where('link.soft_delete', 0)
    ->whereLike('clause.title', '%�%')
    ->count();
$purposeDetail = QmsDocumentStructureService::blockTraceReviewDetail(
    (string)$blocksByType['purpose']['id']
);
$recordDetail = QmsDocumentStructureService::blockTraceReviewDetail(
    (string)$blocksByType['record_requirement']['id']
);
$afterLinks = Db::name('qms_document_block_links')
    ->where('soft_delete', 0)
    ->count();

trace_review_search_assert(
    $beforeLinks === $afterLinks,
    '读取候选优先选项不得新增、修改或删除追溯关系'
);
trace_review_search_assert(
    ($purposeDetail['default_relation_type'] ?? '') === 'implements',
    '普通内容块应保持默认落实手册'
);
trace_review_search_assert(
    ($recordDetail['default_relation_type'] ?? '') === 'requires_record',
    '记录要求块应默认主链：运行记录'
);
trace_review_search_assert(
    $invalidHistoricalLinks > 0,
    '测试库应保留含乱码条款的历史追溯关系作为非破坏性验收基线'
);

$candidateCounts = [];
foreach ([
    'clauses' => '外部条款',
    'manual_sections' => '手册章节',
    'record_forms' => '记录表格',
] as $optionGroup => $label) {
    $rows = (array)($purposeDetail['options'][$optionGroup] ?? []);
    $candidateCounts[$optionGroup] = trace_review_candidate_count($rows);
    trace_review_search_assert(
        $candidateCounts[$optionGroup] > 0,
        $label . '应有本文件候选'
    );
    trace_review_search_assert(
        (bool)($rows[0]['is_candidate'] ?? false),
        $label . '的本文件候选应排在最前'
    );
}

$clauseSummary = (array)(
    $purposeDetail['option_governance_summary']['clauses'] ?? []
);
trace_review_search_assert(
    ($clauseSummary['excluded_invalid'] ?? 0) === 13,
    '新增关系选择器应隔离 13 条疑似乱码外部条款'
);
foreach ((array)($purposeDetail['options']['clauses'] ?? []) as $clause) {
    trace_review_search_assert(
        !str_contains(
            implode(' ', array_map(
                static fn(mixed $value): string =>
                    is_scalar($value) ? (string)$value : '',
                $clause
            )),
            '�'
        ),
        '含 Unicode 替换字符的外部条款不得进入新增关系选择器'
    );
}

$manual64 = array_values(array_filter(
    (array)($purposeDetail['options']['manual_sections'] ?? []),
    static fn(array $row): bool =>
        trim((string)($row['section_number'] ?? '')) === '6.4'
        && trim((string)($row['title'] ?? '')) === '设备'
));
trace_review_search_assert(
    count($manual64) >= 2,
    '6.4 设备应保留治理候选和纸质现用来源供人工核对'
);
trace_review_search_assert(
    (bool)($manual64[0]['is_candidate'] ?? false)
        && !(bool)($manual64[0]['is_secondary'] ?? true)
        && (bool)($manual64[1]['is_secondary'] ?? false),
    '6.4 设备应默认显示候选来源，并把其他来源折叠为历史/其他版本'
);
foreach ($manual64 as $row) {
    trace_review_search_assert(
        trim((string)($row['version_label'] ?? '')) !== ''
            && trim((string)($row['status_label'] ?? '')) !== ''
            && trim((string)($row['governance_label'] ?? '')) !== '',
        '重复手册章节必须显示来源、版本和中文状态'
    );
}

$procedureDuplicates = array_values(array_filter(
    (array)($purposeDetail['options']['procedure_documents'] ?? []),
    static fn(array $row): bool =>
        trim((string)($row['doc_number'] ?? ''))
            === 'SIM-GOV02-XZTC/CX-03-02-2022'
));
trace_review_search_assert(
    count($procedureDuplicates) === 2
        && ($procedureDuplicates[0]['version_label'] ?? '')
            === 'GOV-TRIAL/0.2'
        && !(bool)($procedureDuplicates[0]['is_secondary'] ?? true)
        && (bool)($procedureDuplicates[1]['is_secondary'] ?? false),
    '同编号同标题程序文件应优先 GOV-TRIAL/0.2 并保留另一版本'
);

foreach ((array)($purposeDetail['options']['positions'] ?? []) as $position) {
    trace_review_search_assert(
        array_key_exists('is_candidate', $position)
            && $position['is_candidate'] === false,
        '没有语义候选来源的岗位应明确标为非候选'
    );
}

echo 'qms_trace_review_search_runtime_smoke passed: '
    . json_encode([
        'candidate_counts' => $candidateCounts,
        'excluded_invalid_clauses' =>
            (int)($clauseSummary['excluded_invalid'] ?? 0),
        'invalid_historical_links' => $invalidHistoricalLinks,
        'links_before' => $beforeLinks,
        'links_after' => $afterLinks,
    ], JSON_UNESCAPED_UNICODE)
    . PHP_EOL;
