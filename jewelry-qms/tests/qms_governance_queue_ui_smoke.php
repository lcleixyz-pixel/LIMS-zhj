<?php
declare(strict_types=1);

$root = dirname(__DIR__);

function governance_queue_ui_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function governance_queue_ui_read(string $path): string
{
    $content = file_get_contents($path);
    governance_queue_ui_assert($content !== false, '无法读取：' . $path);

    return $content;
}

$routes = governance_queue_ui_read($root . '/route/app.php');
$controller = governance_queue_ui_read($root . '/app/controller/PlanningStructure.php');
$structureIndex = governance_queue_ui_read($root . '/app/view/planning_structure/index.html');
$workbench = governance_queue_ui_read($root . '/app/view/planning_structure/workbench.html');
$documentIndex = governance_queue_ui_read($root . '/app/view/document/index.html');
$documentController = governance_queue_ui_read($root . '/app/controller/Document.php');
$queuePath = $root . '/app/view/planning_structure/governance_queue.html';

governance_queue_ui_assert(
    str_contains(
        $routes,
        "Route::get('planning/structures/governance-queue', 'PlanningStructure/governanceQueue');"
    ),
    '缺少治理队列 GET 路由'
);
governance_queue_ui_assert(
    str_contains(
        $routes,
        "Route::get('planning/structures/governance-next', 'PlanningStructure/nextGovernance');"
    ),
    '缺少下一份未完成 GET 路由'
);
governance_queue_ui_assert(
    str_contains($controller, 'public function governanceQueue()')
    && str_contains($controller, 'public function nextGovernance()'),
    'PlanningStructure 控制器缺少治理队列动作'
);
governance_queue_ui_assert(is_file($queuePath), '缺少治理队列页面');

$queue = governance_queue_ui_read($queuePath);
foreach ([
    '当前治理队列',
    'name="status"',
    'name="keyword"',
    '<label',
    '已对齐',
    '疑似错挂',
    '主链缺失',
    '候选冲突',
    '继续治理',
    '纸质体系仍为唯一正式体系',
    '没有符合当前筛选条件的文件',
] as $requiredText) {
    governance_queue_ui_assert(
        str_contains($queue, $requiredText),
        '治理队列页面缺少：' . $requiredText
    );
}
governance_queue_ui_assert(
    !str_contains(strtolower($queue), '<form method="post"'),
    '治理队列必须保持只读，不应包含 POST 表单'
);
governance_queue_ui_assert(
    str_contains($structureIndex, '/planning/structures/governance-queue'),
    '结构化文件页缺少治理队列入口'
);
governance_queue_ui_assert(
    str_contains($workbench, '返回治理队列')
    && str_contains($workbench, '下一份未完成')
    && str_contains($workbench, '/planning/structures/governance-next'),
    '治理工作台缺少连续办理入口'
);
governance_queue_ui_assert(
    str_contains($documentIndex, '当前电子治理候选')
    && str_contains($documentIndex, '纸质现用来源')
    && str_contains($documentIndex, '/planning/structures/governance-queue'),
    '体系文件列表缺少版本角色或治理队列入口'
);
governance_queue_ui_assert(
    str_contains($documentController, 'QmsGovernanceVersionResolverService'),
    '体系文件列表尚未接入统一候选版本解析器'
);

echo "qms_governance_queue_ui_smoke passed\n";
