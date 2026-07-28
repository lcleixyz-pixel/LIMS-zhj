<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/app/common.php';

use app\service\QmsDocumentStructureService;
use think\facade\Db;

(new think\App())->initialize();

function trace_link_cards_runtime_assert(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$block = Db::name('qms_document_blocks')
    ->where('id', 'af8ee423-1dad-429f-9399-336922ba7946')
    ->where('soft_delete', 0)
    ->find();
trace_link_cards_runtime_assert(
    is_array($block),
    '8021 缺少 CX-03-02 相关记录内容块'
);

$beforeLinks = (int)Db::name('qms_document_block_links')
    ->where('publish', 1)
    ->where('soft_delete', 0)
    ->count();
$detail = QmsDocumentStructureService::blockTraceReviewDetail(
    (string)$block['id']
);
$afterLinks = (int)Db::name('qms_document_block_links')
    ->where('publish', 1)
    ->where('soft_delete', 0)
    ->count();

trace_link_cards_runtime_assert(
    $beforeLinks === $afterLinks,
    '读取关系卡片不得新增、修改或删除追溯关系'
);

$presentation = (array)($detail['link_presentation'] ?? []);
trace_link_cards_runtime_assert(
    $presentation !== [],
    '复核详情应返回关系卡片展示模型'
);
trace_link_cards_runtime_assert(
    (int)($presentation['total'] ?? -1)
        === count((array)($detail['links'] ?? [])),
    '卡片展示关系数必须与原关系数一致'
);

$presentedLinks = count((array)($presentation['priority'] ?? []));
foreach ((array)($presentation['groups'] ?? []) as $group) {
    $presentedLinks += count((array)($group['links'] ?? []));
}
trace_link_cards_runtime_assert(
    $presentedLinks === (int)$presentation['total'],
    '优先处理区和业务分组合计不得重复或遗漏关系'
);
trace_link_cards_runtime_assert(
    count((array)($presentation['priority'] ?? [])) === 1
        && (bool)(
            $presentation['priority'][0]['relation_policy']['is_mixed']
            ?? false
        ),
    'CX-03-02 相关记录的历史混装关系应进入优先处理区'
);
foreach ((array)$presentation['priority'] as $link) {
    trace_link_cards_runtime_assert(
        count((array)($link['targets'] ?? [])) > 1,
        '历史混装卡片应列出实际关联的多个对象'
    );
}

echo 'qms_trace_link_cards_runtime_smoke passed: '
    . json_encode([
        'total' => (int)$presentation['total'],
        'priority' => count((array)$presentation['priority']),
        'groups' => array_column(
            (array)($presentation['groups'] ?? []),
            'count',
            'key'
        ),
        'links_before' => $beforeLinks,
        'links_after' => $afterLinks,
    ], JSON_UNESCAPED_UNICODE)
    . PHP_EOL;
