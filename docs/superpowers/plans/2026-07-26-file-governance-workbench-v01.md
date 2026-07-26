# 文件治理工作台 v0.1 实现计划

> **面向 AI 代理的工作者：** 必需子技能：使用 `executing-plans` 逐任务实现本计划。步骤使用复选框（`- [ ]`）跟踪进度。本计划不采用子代理，避免共享数据库与同一页面文件并行修改。

**目标：** 在现有文件结构化模块中增加只读“文件治理工作台”，让用户一页看清外部依据、手册条款、程序方法、记录证据、冲突和签批状态，并跳转到现有受控办理页。

**架构：** 新增一个只读聚合服务，将现有结构化文件详情、记录字段覆盖、治理解析稿冲突和文件签批状态组装成稳定 ViewModel。`PlanningStructure` Controller 只负责参数、404 和 View 赋值，Bootstrap 模板只渲染，不自行计算业务状态。所有写操作继续进入现有页面。

**技术栈：** PHP 8、ThinkPHP 8、MySQL 8、Bootstrap 5、现有 source/runtime smoke 脚本、Docker 沙箱镜像。

**运行边界：** 纸质体系仍是唯一正式体系；8021 只用于治理试运行，8010 不写入、不重建。

---

## 文件结构

- 创建 `jewelry-qms/app/service/QmsFileGovernanceWorkbenchService.php`
  - 读取现有服务并组装工作台 ViewModel。
  - 负责证据去重、记录覆盖分类、冲突分层、下一步优先级和只读动作。
- 修改 `jewelry-qms/app/service/QmsDocumentStructureService.php`
  - 为块级追溯结果补充目标对象 ID，保证工作台可按对象 ID 去重。
- 修改 `jewelry-qms/app/service/GovernedTrialResolvedDocumentService.php`
  - 增加只读冲突摘要方法，区分本文件阻断和体系级提醒。
- 创建 `jewelry-qms/app/view/planning_structure/workbench.html`
  - 渲染已确认的“证据链总览”页面。
- 修改 `jewelry-qms/app/controller/PlanningStructure.php`
  - 增加 `workbench()` 只读动作。
- 修改 `jewelry-qms/route/app.php`
  - 增加 GET 路由。
- 修改 `jewelry-qms/app/view/planning_structure/index.html`
  - 在适用文件行增加工作台入口。
- 修改 `jewelry-qms/app/view/planning_structure/view.html`
  - 在结构化文件详情增加工作台入口。
- 创建 `jewelry-qms/tests/qms_file_governance_workbench_service_smoke.php`
  - 测试纯 ViewModel 组装，不依赖数据库。
- 创建 `jewelry-qms/tests/qms_file_governance_workbench_ui_smoke.php`
  - 测试路由、入口、只读边界、页面文案和响应式结构。
- 创建 `jewelry-qms/tests/qms_file_governance_workbench_runtime_smoke.php`
  - 在 8021 数据库上只读验证 CX-03-02、BG-35-03、材料入口和签批回链。
- 创建 `.team/交接箱/2026-07-26-文件治理工作台-v0.1/实施验收记录-v0.1.md`
  - 记录自动验证、8021 验证和 8010 未写入边界。
- 创建 `.team/交接箱/2026-07-26-文件治理工作台-v0.1/版本台账.md`
  - 登记 v0.1 设计、实现和验收状态。

## 通用测试命令

隔离工作树缺少被忽略的 `vendor/`。source smoke 和纯服务测试复用主工作区只读依赖：

```bash
docker run --rm --entrypoint php \
  -v "$PWD/jewelry-qms:/workspace/jewelry-qms" \
  -v "/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj/jewelry-qms/vendor:/workspace/jewelry-qms/vendor:ro" \
  -w /workspace/jewelry-qms \
  jewelry-qms-app \
  tests/qms_file_governance_workbench_service_smoke.php
```

8021 运行测试使用现有沙箱网络，只执行 SELECT：

```bash
docker run --rm --entrypoint php \
  --network lims-zhj-governance-trial-20260724_default \
  -e DB_TYPE=mysql \
  -e DB_HOST=db \
  -e DB_NAME=jewelry_qms \
  -e DB_USER=root \
  -e DB_PASS= \
  -e DB_PORT=3306 \
  -e DB_CHARSET=utf8mb4 \
  -e QMS_TRIAL_MODE=true \
  -e QMS_TRIAL_BATCH=GOV-TRIAL-20260724 \
  -v "$PWD/jewelry-qms:/app" \
  -v "/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj/jewelry-qms/vendor:/app/vendor:ro" \
  -v "$PWD/.team:/.team:ro" \
  -w /app \
  jewelry-qms-app \
  tests/qms_file_governance_workbench_runtime_smoke.php
```

### 任务 1：锁定追溯 ID 和冲突分层契约

**文件：**

- 修改：`jewelry-qms/app/service/QmsDocumentStructureService.php`
- 修改：`jewelry-qms/app/service/GovernedTrialResolvedDocumentService.php`
- 创建：`jewelry-qms/tests/qms_file_governance_workbench_service_smoke.php`

- [ ] **步骤 1：编写失败的追溯与冲突契约测试**

先创建测试骨架，要求追溯查询包含对象 ID，并要求解析稿服务提供本文件冲突摘要：

```php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\service\GovernedTrialResolvedDocumentService;

function workbench_service_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__);
$structureSource = (string)file_get_contents($root . '/app/service/QmsDocumentStructureService.php');
$resolvedSource = (string)file_get_contents($root . '/app/service/GovernedTrialResolvedDocumentService.php');

preg_match(
    '/private static function linksForBlock\\(string \\$blockId\\): array.*?->toArray\\(\\);/s',
    $structureSource,
    $linksMethod
);
workbench_service_assert($linksMethod !== [], '未找到 linksForBlock 方法源码');
foreach ([
    'l.clause_id',
    'l.manual_section_id',
    'l.procedure_document_id',
    'l.record_form_template_id',
    'l.position_id',
    'l.business_module_id',
] as $requiredField) {
    workbench_service_assert(
        str_contains((string)$linksMethod[0], $requiredField),
        '块级追溯结果缺少对象 ID：' . $requiredField
    );
}

workbench_service_assert(
    str_contains($resolvedSource, 'public static function currentConflictSummary'),
    '治理解析稿服务应提供只读冲突摘要'
);

$summary = GovernedTrialResolvedDocumentService::splitConflictSummary([
    'blocking_conflicts' => [
        ['doc_number' => 'XZTC/CX-03-02-2022', 'message' => '当前文件冲突'],
        ['doc_number' => 'XZTC/CX-21-2022', 'message' => '其他文件冲突'],
    ],
    'warnings' => [
        ['doc_number' => 'SYSTEM', 'message' => '体系提醒'],
    ],
], 'XZTC/CX-03-02-2022');

workbench_service_assert(count($summary['document_blockers']) === 1, '应只保留当前文件阻断');
workbench_service_assert(count($summary['system_notices']) === 2, '其他文件冲突和体系提醒应归入体系提示');

echo "qms_file_governance_workbench_service_smoke contract passed\n";
```

- [ ] **步骤 2：运行测试，确认因契约缺失而失败**

运行通用服务测试命令。

预期：FAIL，提示“治理解析稿服务应提供只读冲突摘要”或方法不存在。

- [ ] **步骤 3：为块级追溯结果补充对象 ID**

在 `linksForBlock()` 的 `field()` 中追加：

```php
'l.clause_id,l.manual_section_id,l.procedure_document_id,'
. 'l.record_form_template_id,l.position_id,l.business_module_id,'
```

保留现有展示字段，不删除或改名。

- [ ] **步骤 4：实现只读冲突摘要**

在 `GovernedTrialResolvedDocumentService` 增加：

```php
public static function currentConflictSummary(string $docNumber): array
{
    $path = self::workspaceRoot() . '/' . self::DEFAULT_OUTPUT . '/冲突审查/冲突总表.json';
    if (!is_file($path)) {
        return [
            'available' => false,
            'document_blockers' => [],
            'system_notices' => [[
                'doc_number' => 'SYSTEM',
                'message' => '冲突审查材料尚未生成。',
                'blocking' => false,
            ]],
        ];
    }

    $decoded = json_decode((string)file_get_contents($path), true);
    if (!is_array($decoded)) {
        return [
            'available' => false,
            'document_blockers' => [],
            'system_notices' => [[
                'doc_number' => 'SYSTEM',
                'message' => '冲突审查材料无法读取。',
                'blocking' => false,
            ]],
        ];
    }

    return ['available' => true] + self::splitConflictSummary($decoded, $docNumber);
}

public static function splitConflictSummary(array $report, string $docNumber): array
{
    $documentBlockers = [];
    $systemNotices = [];
    foreach ((array)($report['blocking_conflicts'] ?? []) as $row) {
        if ((string)($row['doc_number'] ?? '') === $docNumber) {
            $documentBlockers[] = $row;
        } else {
            $systemNotices[] = $row;
        }
    }
    foreach ((array)($report['warnings'] ?? []) as $row) {
        $systemNotices[] = $row;
    }

    return [
        'document_blockers' => array_values($documentBlockers),
        'system_notices' => array_values($systemNotices),
    ];
}
```

- [ ] **步骤 5：运行测试，确认契约通过**

运行通用服务测试命令。

预期：

```text
qms_file_governance_workbench_service_smoke contract passed
```

- [ ] **步骤 6：提交任务 1**

```bash
git add jewelry-qms/app/service/QmsDocumentStructureService.php \
  jewelry-qms/app/service/GovernedTrialResolvedDocumentService.php \
  jewelry-qms/tests/qms_file_governance_workbench_service_smoke.php
git commit -m "feat(qms): 补充文件治理证据读取契约"
```

### 任务 2：实现只读工作台 ViewModel

**文件：**

- 创建：`jewelry-qms/app/service/QmsFileGovernanceWorkbenchService.php`
- 修改：`jewelry-qms/tests/qms_file_governance_workbench_service_smoke.php`

- [ ] **步骤 1：扩展失败测试，定义 ViewModel 行为**

在任务 1 测试中追加一个最小快照。快照包含重复追溯、一个字段已覆盖记录和一个待复核记录：

```php
use app\service\QmsFileGovernanceWorkbenchService;

$viewModel = QmsFileGovernanceWorkbenchService::fromSnapshot(
    [
        'document' => [
            'id' => 'structured-cx0302',
            'document_id' => 'document-cx0302',
            'document_role' => 'procedure',
            'doc_number' => 'XZTC/CX-03-02-2022',
            'title' => '标准物质管理程序',
            'version' => 'GOV-TRIAL/0.2',
            'status' => 'draft',
        ],
        'blocks' => [
            [
                'block' => [
                    'id' => 'block-1',
                    'title' => '职责',
                    'block_type' => 'section',
                ],
                'links' => [
                    [
                        'clause_id' => 'clause-64',
                        'source_code' => 'CNAS-CL01',
                        'clause_number' => '6.4',
                        'manual_section_id' => 'manual-64',
                        'section_number' => '6.4',
                        'manual_title' => '标准物质',
                        'record_form_template_id' => 'form-bg3503',
                        'record_number' => 'XZTC/BG-35-03',
                        'record_name' => '标准物质报废申请表',
                    ],
                    [
                        'clause_id' => 'clause-64',
                        'source_code' => 'CNAS-CL01',
                        'clause_number' => '6.4',
                        'manual_section_id' => 'manual-64',
                        'section_number' => '6.4',
                        'manual_title' => '标准物质',
                        'record_form_template_id' => 'form-bg3503',
                        'record_number' => 'XZTC/BG-35-03',
                        'record_name' => '标准物质报废申请表',
                    ],
                ],
            ],
        ],
    ],
    [
        [
            'block_id' => 'block-1',
            'coverage_status' => 'covered',
            'linked_record_forms' => 1,
            'schema_field_count' => 8,
            'record_form_labels' => 'XZTC/BG-35-03 标准物质报废申请表',
            'trace_review_url' => '/planning/structures/links/review?block_id=block-1',
        ],
    ],
    [
        'is_resolved_trial' => true,
        'continuous_url' => '/continuous',
        'comparison_url' => '/comparison',
        'conflicts_url' => '/conflicts',
    ],
    [
        'available' => true,
        'document_blockers' => [],
        'system_notices' => [],
    ],
    [
        'id' => 'document-cx0302',
        'status' => 'draft',
        'doc_number' => 'SIM-GOV02-XZTC/CX-03-02-2022',
    ],
    [
        'stage' => 'draft',
        'stage_label' => '草稿，等待提交',
    ]
);

workbench_service_assert(count($viewModel['chain']['external_sources']) === 1, '外部依据应按 ID 去重');
workbench_service_assert(count($viewModel['chain']['manual_sections']) === 1, '手册条款应按 ID 去重');
workbench_service_assert(count($viewModel['chain']['record_evidence']) === 1, '记录表格应按 ID 去重');
workbench_service_assert($viewModel['record_coverage']['covered'] === 1, '应识别字段已覆盖记录');
workbench_service_assert($viewModel['summary']['level'] === 'ready', '无断链无阻断的草稿应可继续试运行');
workbench_service_assert($viewModel['actions'][0]['url'] === '/document/view?id=document-cx0302', '下一步应进入现有文件页提交');
```

- [ ] **步骤 2：运行测试，确认因服务不存在而失败**

运行通用服务测试命令。

预期：FAIL，提示 `Class "app\service\QmsFileGovernanceWorkbenchService" not found`。

- [ ] **步骤 3：实现 ViewModel 纯组装**

创建 `QmsFileGovernanceWorkbenchService`，提供：

```php
public static function fromSnapshot(
    array $detail,
    array $schemaRows,
    array $artifacts,
    array $conflicts,
    array $controlledDocument,
    array $workflow
): array
```

实现规则：

- 用 `clause_id`、`manual_section_id`、`record_form_template_id`、`business_module_id` 去重；
- ID 为空时使用“编号 + 标题”作为兼容键；
- 输入中的 ThinkPHP Model 先通过 `toArray()` 转为数组，测试快照数组保持原样；
- 程序落实方法使用当前有效内容块；
- `coverage_status=covered` 计入 `covered`；
- `coverage_status=gap` 且 `linked_record_forms>0` 计入 `needs_review`；
- `coverage_status=gap` 且 `linked_record_forms=0` 计入 `missing`；
- 有任一外部依据、手册条款或程序块断链时，`summary.level=blocked`；
- 无断链但记录有缺失、待复核或本文件冲突时，`summary.level=warning`；
- 全部检查通过且签批已完成时，`summary.level=completed`；
- 其他情况为 `ready`；
- 下一步严格按规格第 4.5 节排序，只返回已有 URL；
- 所有动作结构统一为：

```php
[
    'type' => 'trace|record|conflict|document|complete',
    'label' => '前往记录支撑复核',
    'description' => 'BG-35-03 字段仍需确认',
    'url' => '/planning/structures/links/review?block_id=block-1',
    'enabled' => true,
    'disabled_reason' => '',
]
```

- [ ] **步骤 4：运行测试，确认 ViewModel 测试通过**

运行通用服务测试命令。

预期：任务 1 契约断言和 ViewModel 断言全部通过。

- [ ] **步骤 5：增加断链和历史文件边界测试**

追加 3 个快照断言：

```php
// 没有手册条款：blocked，第一动作进入首个内容块追溯复核。
// 记录要求已关联但 schema 为空：needs_review，不冒充 covered。
// status=obsolete：不产生 document 类型的提交动作。
```

再次运行服务测试，先确认新增断言能捕获未实现行为，再补最少代码使其通过。

- [ ] **步骤 6：提交任务 2**

```bash
git add jewelry-qms/app/service/QmsFileGovernanceWorkbenchService.php \
  jewelry-qms/tests/qms_file_governance_workbench_service_smoke.php
git commit -m "feat(qms): 组装文件治理工作台状态"
```

### 任务 3：接入真实数据、路由和 Controller

**文件：**

- 修改：`jewelry-qms/app/service/QmsFileGovernanceWorkbenchService.php`
- 修改：`jewelry-qms/app/controller/PlanningStructure.php`
- 修改：`jewelry-qms/route/app.php`
- 创建：`jewelry-qms/tests/qms_file_governance_workbench_ui_smoke.php`

- [ ] **步骤 1：编写失败的路由与只读边界测试**

创建 source smoke：

```php
<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$route = (string)file_get_contents($root . '/route/app.php');
$controller = (string)file_get_contents($root . '/app/controller/PlanningStructure.php');
$service = (string)file_get_contents($root . '/app/service/QmsFileGovernanceWorkbenchService.php');

function workbench_ui_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

workbench_ui_assert(
    str_contains($route, "Route::get('planning/structures/workbench'"),
    '应提供只读工作台 GET 路由'
);
workbench_ui_assert(
    str_contains($controller, 'public function workbench()'),
    'PlanningStructure 应提供 workbench 动作'
);
workbench_ui_assert(
    str_contains($service, 'public static function detail('),
    '工作台服务应提供真实数据入口'
);

echo "qms_file_governance_workbench_ui_smoke route passed\n";
```

- [ ] **步骤 2：运行测试，确认路由和动作缺失**

运行：

```bash
docker run --rm --entrypoint php \
  -v "$PWD/jewelry-qms:/workspace/jewelry-qms" \
  -w /workspace/jewelry-qms \
  jewelry-qms-app \
  tests/qms_file_governance_workbench_ui_smoke.php
```

预期：FAIL，提示“应提供只读工作台 GET 路由”。

- [ ] **步骤 3：实现真实数据入口**

在服务增加：

```php
public static function detail(string $structuredId, string $currentUserId = ''): array
```

按顺序读取：

1. `QmsDocumentStructureService::structuredDocumentDetail($structuredId)`；
2. `QmsDocumentStructureService::recordRequirementSchemaCoverage()['rows']` 并按 `structured_document_id` 过滤；
3. `GovernedTrialResolvedDocumentService::resolvedArtifactLinks($structured)`；
4. `GovernedTrialResolvedDocumentService::currentConflictSummary($docNumber)`；
5. 若 `document_id` 有值，读取 `Document`；
6. 若受控文件存在，调用 `ApprovalService::documentWorkflowStatus()`；
7. 调用 `fromSnapshot()`。

所有读取包在 `try/catch` 内。签批状态单独失败时，传入：

```php
[
    'stage' => 'unavailable',
    'stage_label' => '签批状态暂时无法读取',
]
```

不得捕获后返回“可提交”。

- [ ] **步骤 4：增加 Controller 与 GET 路由**

Controller：

```php
public function workbench()
{
    $workbench = QmsFileGovernanceWorkbenchService::detail(
        (string)$this->request->param('id', ''),
        (string)Session::get('user.id', '')
    );
    if ($workbench === []) {
        throw new HttpException(404, '结构化文件不存在');
    }

    View::assign('workbench', $workbench);

    return View::fetch('planning_structure/workbench');
}
```

路由必须放在通用 `planning/structures` 规则之前：

```php
Route::get('planning/structures/workbench', 'PlanningStructure/workbench');
```

- [ ] **步骤 5：运行 UI smoke，确认路由部分通过**

预期：

```text
qms_file_governance_workbench_ui_smoke route passed
```

- [ ] **步骤 6：提交任务 3**

```bash
git add jewelry-qms/app/service/QmsFileGovernanceWorkbenchService.php \
  jewelry-qms/app/controller/PlanningStructure.php \
  jewelry-qms/route/app.php \
  jewelry-qms/tests/qms_file_governance_workbench_ui_smoke.php
git commit -m "feat(qms): 接入文件治理工作台路由"
```

### 任务 4：实现已确认的证据链总览页面

**文件：**

- 创建：`jewelry-qms/app/view/planning_structure/workbench.html`
- 修改：`jewelry-qms/app/view/planning_structure/index.html`
- 修改：`jewelry-qms/app/view/planning_structure/view.html`
- 修改：`jewelry-qms/tests/qms_file_governance_workbench_ui_smoke.php`

- [ ] **步骤 1：扩展失败的页面结构测试**

在 UI smoke 中读取 3 个模板并断言：

```php
$workbenchView = (string)file_get_contents($root . '/app/view/planning_structure/workbench.html');
$indexView = (string)file_get_contents($root . '/app/view/planning_structure/index.html');
$detailView = (string)file_get_contents($root . '/app/view/planning_structure/view.html');

foreach ([
    '系统设计链条',
    '外部依据',
    '手册条款',
    '程序落实方法',
    '运行证据',
    '文件内容与审查材料',
    '下一步动作',
    '纸质体系仍为唯一正式体系',
] as $text) {
    workbench_ui_assert(str_contains($workbenchView, $text), '工作台缺少文案：' . $text);
}

workbench_ui_assert(
    str_contains($workbenchView, 'workbench.chain.external_sources'),
    '外部依据数量必须来自 ViewModel'
);
workbench_ui_assert(
    !str_contains($workbenchView, '<form'),
    'v0.1 工作台不得包含写入表单'
);
workbench_ui_assert(
    str_contains($indexView, '/planning/structures/workbench?id='),
    '结构化文件列表应提供工作台入口'
);
workbench_ui_assert(
    str_contains($detailView, '/planning/structures/workbench?id='),
    '结构化文件详情应提供工作台入口'
);
workbench_ui_assert(
    str_contains($workbenchView, 'col-12 col-lg-8')
    && str_contains($workbenchView, 'col-12 col-lg-4'),
    '工作台应明确提供窄屏单列和宽屏双列布局'
);
```

- [ ] **步骤 2：运行测试，确认页面缺失**

运行 UI smoke。

预期：FAIL，提示工作台模板不存在或缺少页面文案。

- [ ] **步骤 3：创建工作台模板**

页面按以下顺序渲染：

1. `qms-page-header`：文件编号、标题、版本、边界提示、返回列表；
2. `alert`：当前结论和下一步；
3. 4 段系统设计链；
4. `row g-3`：左侧材料与证据，右侧下一步动作；
5. 冲突分层；
6. 签批状态和文件详情入口。

状态映射：

```php
ready     -> alert-success / badge-status-effective
warning   -> alert-warning / bg-warning text-dark
blocked   -> alert-danger / badge-status-obsolete
completed -> alert-success / badge-status-effective
```

不要把状态类名直接信任为任意 HTML；模板用 `{if}` 明确映射。

空状态示例：

```html
<div class="qms-empty-state">
    尚未找到手册条款追溯。下一步：打开内容块追溯复核，补充适用手册章节。
</div>
```

禁用动作必须同时输出 `disabled_reason`。

- [ ] **步骤 4：增加列表和详情入口**

只对以下角色显示：

```php
['quality_manual', 'procedure', 'work_instruction']
```

列表链接使用：

```html
<a href="/planning/structures/workbench?id={$row.document.id}"
   class="btn btn-sm btn-outline-success">治理工作台</a>
```

详情页链接使用：

```html
<a href="/planning/structures/workbench?id={$detail.document.id}"
   class="btn btn-sm btn-success">治理工作台</a>
```

- [ ] **步骤 5：运行 UI smoke，确认页面通过**

预期：

```text
qms_file_governance_workbench_ui_smoke route passed
```

- [ ] **步骤 6：做页面预检**

检查：

- 所有按钮有可读文字；
- 无空提示框；
- 无颜色单独表达状态；
- 无重复主动作；
- 窄屏为单列；
- 没有 `<form>`、POST、删除或发布动作；
- 按钮文字在桌面宽度不换行。

- [ ] **步骤 7：提交任务 4**

```bash
git add jewelry-qms/app/view/planning_structure/workbench.html \
  jewelry-qms/app/view/planning_structure/index.html \
  jewelry-qms/app/view/planning_structure/view.html \
  jewelry-qms/tests/qms_file_governance_workbench_ui_smoke.php
git commit -m "feat(qms): 展示文件治理证据链总览"
```

### 任务 5：8021 只读运行验证和交接

**文件：**

- 创建：`jewelry-qms/tests/qms_file_governance_workbench_runtime_smoke.php`
- 创建：`.team/交接箱/2026-07-26-文件治理工作台-v0.1/实施验收记录-v0.1.md`
- 创建：`.team/交接箱/2026-07-26-文件治理工作台-v0.1/版本台账.md`
- 修改：`.team/交接箱/2026-07-26-主线合并与下一阶段规划/进度卡-v0.1.md`

- [ ] **步骤 1：编写只读运行测试**

测试必须：

```php
<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\QmsFileGovernanceWorkbenchService;
use think\facade\Db;

(new think\App())->initialize();

function workbench_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$structured = Db::name('qms_structured_documents')
    ->where('doc_number', 'XZTC/CX-03-02-2022')
    ->where('version', 'GOV-TRIAL/0.2')
    ->where('soft_delete', 0)
    ->find();
workbench_runtime_assert(is_array($structured), '8021 缺少 CX-03-02 GOV-TRIAL/0.2');

$before = [
    'structures' => Db::name('qms_structured_documents')->where('soft_delete', 0)->count(),
    'links' => Db::name('qms_document_block_links')->where('soft_delete', 0)->count(),
    'documents' => Db::name('documents')->where('soft_delete', 0)->count(),
];
$viewModel = QmsFileGovernanceWorkbenchService::detail((string)$structured['id']);
$after = [
    'structures' => Db::name('qms_structured_documents')->where('soft_delete', 0)->count(),
    'links' => Db::name('qms_document_block_links')->where('soft_delete', 0)->count(),
    'documents' => Db::name('documents')->where('soft_delete', 0)->count(),
];

workbench_runtime_assert($before === $after, '工作台读取不得改变数据库计数');
workbench_runtime_assert($viewModel !== [], 'CX-03-02 工作台不得为空');
workbench_runtime_assert(
    array_filter(
        $viewModel['chain']['record_evidence'],
        static fn(array $row): bool =>
            ($row['doc_number'] ?? '') === 'XZTC/BG-35-03'
            && str_contains((string)($row['name'] ?? ''), '标准物质报废申请表')
    ) !== [],
    'BG-35-03 应保持标准物质报废申请表治理映射'
);
workbench_runtime_assert(
    ($viewModel['artifacts']['continuous_url'] ?? '') !== '',
    'CX-03-02 应提供连续正文入口'
);
workbench_runtime_assert(
    ($viewModel['document']['document_url'] ?? '') !== '',
    'CX-03-02 应回链文件详情和签批状态'
);

echo "qms_file_governance_workbench_runtime_smoke passed\n";
```

- [ ] **步骤 2：运行测试，确认只读真实数据通过**

运行“8021 运行测试”命令。

预期：

```text
qms_file_governance_workbench_runtime_smoke passed
```

- [ ] **步骤 3：运行语法与回归测试**

语法：

```bash
docker run --rm --entrypoint sh \
  -v "$PWD/jewelry-qms:/workspace/jewelry-qms" \
  -w /workspace/jewelry-qms \
  jewelry-qms-app \
  -lc 'set -eu; find app config route -type f -name "*.php" -print0 |
    while IFS= read -r -d "" file; do php -l "$file" >/dev/null; done'
```

专项回归：

```bash
for test_file in \
  qms_file_governance_workbench_service_smoke.php \
  qms_file_governance_workbench_ui_smoke.php \
  qms_governed_trial_resolved_manifest_smoke.php \
  qms_governed_trial_resolved_documents_smoke.php \
  qms_governance_trial_signing_ui_smoke.php \
  qms_record_form_template_revision_smoke.php \
  qms_flash_message_ui_smoke.php
do
  docker run --rm --entrypoint php \
    -v "$PWD/jewelry-qms:/workspace/jewelry-qms" \
    -v "/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj/jewelry-qms/vendor:/workspace/jewelry-qms/vendor:ro" \
    -w /workspace/jewelry-qms \
    jewelry-qms-app \
    "tests/$test_file"
done
```

预期：全部显示 `passed`，无 PHP Warning 或 Fatal error。

- [ ] **步骤 4：浏览器验收**

在 8021 同源沙箱加载分支代码后检查：

1. 打开文件结构化列表；
2. 进入 CX-03-02“治理工作台”；
3. 核对 4 段证据链；
4. 打开连续正文、修订对照、冲突审查；
5. 进入 BG-35-03 记录支撑复核；
6. 返回工作台；
7. 用编制人、审核人、批准人确认只读内容一致；
8. 确认无空提示框、无页面错误、无失效按钮。

- [ ] **步骤 5：形成验收记录和版本台账**

`实施验收记录-v0.1.md` 至少登记：

- 分支与提交；
- 设计规格；
- 自动测试结果；
- 8021 实测对象；
- 只读计数前后对比；
- 8010 未部署、未写入；
- 已知限制；
- 用户验收入口。

`版本台账.md` 只增不改，首行登记：

```markdown
| v0.1 | 2026-07-26 | CX-03-02 证据链总览、冲突分层、记录字段覆盖、签批回链 | 待用户验收 |
```

- [ ] **步骤 6：提交任务 5**

```bash
git add jewelry-qms/tests/qms_file_governance_workbench_runtime_smoke.php \
  .team/交接箱/2026-07-26-文件治理工作台-v0.1 \
  .team/交接箱/2026-07-26-主线合并与下一阶段规划/进度卡-v0.1.md
git commit -m "test(qms): 验证文件治理工作台闭环"
```

## 最终验证

- [ ] `git diff --check` 无输出。
- [ ] PHP 语法检查全部通过。
- [ ] 3 个新 smoke 全部通过。
- [ ] 既有治理解析稿、签批、模板换版和反馈 smoke 全部通过。
- [ ] 8021 CX-03-02 工作台只读计数前后一致。
- [ ] BG-35-03 显示为“标准物质报废申请表”。
- [ ] 8010 未重建镜像、未运行迁移、未写数据库。
- [ ] 工作区只包含本功能相关提交。
- [ ] 验收记录、版本台账和进度卡已更新。
