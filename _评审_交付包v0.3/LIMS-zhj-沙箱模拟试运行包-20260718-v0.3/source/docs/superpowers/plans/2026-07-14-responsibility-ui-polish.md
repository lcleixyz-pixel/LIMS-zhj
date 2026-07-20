# 责任链 UI 中文化与流程优化实现计划

> **面向 AI 代理的工作者：** 必需子技能：使用 superpowers:subagent-driven-development（推荐）或 superpowers:executing-plans 逐任务实现此计划。步骤使用复选框（`- [ ]`）语法来跟踪进度。

**目标：** 将活动级责任链从暴露英文状态和开发字段的页面，改造成中文、按四步流程引导、技术证据按需展开的业务界面，同时保持既有规则和数据库不变。

**架构：** 新增一个只负责展示映射的纯 PHP 服务，把内部状态、职责类型、人员确定方式和岗位代码转换为中文展示数据；控制器只装配展示数据，模板只负责布局；责任链专属 CSS 使用模块作用域，不影响其他页面。所有改变先通过失败测试定义，再做最小实现。

**技术栈：** ThinkPHP 8、PHP 8.4、ThinkPHP 服务端模板、Bootstrap 5.3、原生 CSS、Docker Compose、现有 PHP smoke tests。

---

## 文件结构与职责

- 创建 `jewelry-qms/app/service/QmsResponsibilityPresentationService.php`：集中维护中文映射、页面摘要和展示数据装饰；不查询数据库，不作业务判断。
- 创建 `jewelry-qms/tests/qms_responsibility_presentation_smoke.php`：验证全部内部值的中文映射、未知值兜底和摘要计数。
- 修改 `jewelry-qms/app/controller/PlanningResponsibility.php`：调用展示服务并把中文化后的版本、详情、校验、签批、任命和对齐数据交给模板。
- 修改 `jewelry-qms/app/view/planning_responsibility/index.html`：实现四步导航、业务提示、带标签的人员表单和技术详情折叠。
- 创建 `jewelry-qms/public/static/css/qms-responsibility.css`：提供责任链模块专属布局和响应式样式。
- 修改 `jewelry-qms/app/view/layout/main.html`：加载责任链专属样式。
- 修改 `jewelry-qms/tests/qms_responsibility_ui_smoke.php`：验证渲染页面的中文主流程、技术信息渐进披露和原有运行时职责边界。
- 修改 `jewelry-qms/tests/qms_ui_navigation_template_smoke.php`：验证新增样式被加载且全局业务导航未退化。
- 创建 `.team/交接箱/2026-07-14-责任链UI整治-v0.1/验收记录-v0.1.md`：登记红灯、绿灯、回归和人工验收结果。

## 统一验证环境

所有测试都在工作树 `LIMS-zhj/.worktrees/responsibility-ui-polish/jewelry-qms` 中运行，使用独立 Compose 项目，禁止连接本机 8010 数据库：

```bash
PROJECT=lzhj-responsibility-ui-dev-0714
docker compose -f compose.yaml -f compose.responsibility-test.yaml -p "$PROJECT" up -d db
```

单项测试模板：

```bash
docker compose -f compose.yaml -f compose.responsibility-test.yaml -p "$PROJECT" \
  run --rm app php tests/<测试文件>.php
```

收尾清理：

```bash
docker compose -f compose.yaml -f compose.responsibility-test.yaml -p "$PROJECT" \
  down -v --remove-orphans
```

### 任务 1：建立责任链展示词汇表

**文件：**
- 创建：`jewelry-qms/tests/qms_responsibility_presentation_smoke.php`
- 创建：`jewelry-qms/app/service/QmsResponsibilityPresentationService.php`

- [ ] **步骤 1：编写失败的映射测试**

创建测试，逐项断言设计规格中的内部值都映射为中文，并验证未知内部值不会空白：

```php
<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use app\service\QmsResponsibilityPresentationService as Presenter;

function presentation_assert_same(string $expected, string $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . " expected={$expected} actual={$actual}\n");
        exit(1);
    }
}

$cases = [
    ['version', 'draft', '草案'],
    ['version', 'pending_approval', '待签批'],
    ['version', 'effective', '已生效'],
    ['version', 'superseded', '已被新版本替代'],
    ['version', 'revoked', '已撤销'],
    ['assignment', 'active', '有效'],
    ['assignment', 'expired', '已到期'],
    ['validation', 'pass', '可以继续'],
    ['validation', 'warning', '需要补充'],
    ['validation', 'blocker', '暂不能提交'],
    ['alignment', 'conflict', '存在冲突'],
    ['alignment', 'review_required', '需要人工确认'],
    ['alignment', 'aligned', '一致'],
    ['duty', 'organize', '组织'],
    ['duty', 'review', '审核'],
    ['duty', 'approve', '批准'],
    ['duty', 'execute', '执行'],
    ['duty', 'verify', '验证'],
    ['duty', 'record_keep', '记录与归档'],
    ['duty', 'countersign', '会签'],
    ['duty', 'inform', '提供信息'],
    ['assignment_mode', 'named_person', '需提前指定具体人员'],
    ['assignment_mode', 'activity_instance', '在具体活动开始时指定'],
    ['assignment_mode', 'derived_from_scope', '根据活动范围确定后人工核对'],
    ['decision', 'approved', '已批准'],
    ['decision', 'rejected', '已驳回'],
    ['decision', 'pending', '待处理'],
    ['severity', 'warning', '提醒'],
    ['severity', 'blocker', '阻断'],
    ['position', 'company_general_manager', '公司总经理'],
    ['position', 'lab_director', '实验室主任'],
    ['position', 'quality_manager', '质量负责人'],
    ['position', 'technical_manager', '技术负责人'],
    ['position', 'document_controller', '资料管理员'],
    ['role', 'audit_leader', '审核组长'],
    ['role', 'internal_auditor', '内审员'],
    ['role', 'audited_activity_owner', '被审核活动责任人'],
    ['role', 'audit_follow_up_verifier', '整改跟踪验证人'],
    ['role', 'management_review_input_owner', '管理评审输入责任人'],
    ['role', 'management_review_improvement_owner', '改进措施责任人'],
    ['role', 'risk_owner', '风险责任人'],
    ['role', 'risk_treatment_owner', '风险处置责任人'],
    ['role', 'risk_verifier', '风险措施验证人'],
];

foreach ($cases as [$group, $value, $expected]) {
    presentation_assert_same($expected, Presenter::label($group, $value), "label {$group}.{$value}");
}
presentation_assert_same('未识别状态', Presenter::label('version', 'unexpected'), 'unknown value fallback');

echo "qms_responsibility_presentation_smoke passed\n";
```

- [ ] **步骤 2：运行测试验证红灯**

运行：

```bash
docker compose -f compose.yaml -f compose.responsibility-test.yaml -p "$PROJECT" \
  run --rm app php tests/qms_responsibility_presentation_smoke.php
```

预期：退出码非 0，报错包含 `Class "app\service\QmsResponsibilityPresentationService" not found`。

- [ ] **步骤 3：实现最小展示服务**

创建纯展示服务；映射值必须与测试完全一致：

```php
<?php
declare(strict_types=1);

namespace app\service;

final class QmsResponsibilityPresentationService
{
    private const LABELS = [
        'version' => [
            'draft' => '草案',
            'pending_approval' => '待签批',
            'effective' => '已生效',
            'superseded' => '已被新版本替代',
            'revoked' => '已撤销',
        ],
        'assignment' => [
            'draft' => '草案',
            'pending_approval' => '待签批',
            'active' => '有效',
            'revoked' => '已撤销',
            'expired' => '已到期',
        ],
        'validation' => [
            'pass' => '可以继续',
            'warning' => '需要补充',
            'blocker' => '暂不能提交',
        ],
        'alignment' => [
            'conflict' => '存在冲突',
            'review_required' => '需要人工确认',
            'aligned' => '一致',
        ],
        'duty' => [
            'organize' => '组织',
            'review' => '审核',
            'approve' => '批准',
            'execute' => '执行',
            'verify' => '验证',
            'record_keep' => '记录与归档',
            'countersign' => '会签',
            'inform' => '提供信息',
        ],
        'assignment_mode' => [
            'named_person' => '需提前指定具体人员',
            'activity_instance' => '在具体活动开始时指定',
            'derived_from_scope' => '根据活动范围确定后人工核对',
        ],
        'decision' => [
            'approved' => '已批准',
            'rejected' => '已驳回',
            'pending' => '待处理',
        ],
        'severity' => [
            'warning' => '提醒',
            'blocker' => '阻断',
        ],
        'position' => [
            'company_general_manager' => '公司总经理',
            'lab_director' => '实验室主任',
            'quality_manager' => '质量负责人',
            'technical_manager' => '技术负责人',
            'document_controller' => '资料管理员',
        ],
        'role' => [
            'audit_leader' => '审核组长',
            'internal_auditor' => '内审员',
            'audited_activity_owner' => '被审核活动责任人',
            'audit_follow_up_verifier' => '整改跟踪验证人',
            'management_review_input_owner' => '管理评审输入责任人',
            'management_review_improvement_owner' => '改进措施责任人',
            'risk_owner' => '风险责任人',
            'risk_treatment_owner' => '风险处置责任人',
            'risk_verifier' => '风险措施验证人',
        ],
    ];

    public static function label(string $group, string $value): string
    {
        return self::LABELS[$group][$value] ?? '未识别状态';
    }
}
```

- [ ] **步骤 4：运行映射测试验证绿灯**

运行同一步骤 2。预期：退出码 0，末行 `qms_responsibility_presentation_smoke passed`。

- [ ] **步骤 5：提交词汇表**

```bash
git add jewelry-qms/app/service/QmsResponsibilityPresentationService.php \
  jewelry-qms/tests/qms_responsibility_presentation_smoke.php
git commit -m "feat(qms): 建立责任链中文展示词汇表"
```

### 任务 2：生成页面专用展示数据与摘要

**文件：**
- 修改：`jewelry-qms/tests/qms_responsibility_presentation_smoke.php`
- 修改：`jewelry-qms/app/service/QmsResponsibilityPresentationService.php`
- 修改：`jewelry-qms/app/controller/PlanningResponsibility.php:8-14,284-354`

- [ ] **步骤 1：为展示装饰与摘要编写失败测试**

在映射测试末尾加入：

```php
$detail = Presenter::detail([
    'status' => 'draft',
    'chain_code' => 'core_governance',
    'activities' => [[
        'responsibilities' => [
            ['duty_type' => 'approve', 'assignment_mode' => 'named_person', 'assignments' => []],
            ['duty_type' => 'verify', 'assignment_mode' => 'activity_instance', 'assignments' => []],
        ],
    ]],
]);
presentation_assert_same('草案', (string)$detail['status_label'], 'detail status label');
presentation_assert_same('批准', (string)$detail['activities'][0]['responsibilities'][0]['duty_type_label'], 'duty label');
presentation_assert_same('2', (string)$detail['summary']['responsibility_count'], 'responsibility count');
presentation_assert_same('1', (string)$detail['summary']['named_person_count'], 'named-person count');
presentation_assert_same('1', (string)$detail['summary']['runtime_count'], 'runtime count');

$validation = Presenter::validation([
    'result' => 'blocker',
    'issues' => [['severity' => 'warning', 'message' => '示例提醒']],
]);
presentation_assert_same('暂不能提交', (string)$validation['result_label'], 'validation result label');
presentation_assert_same('提醒', (string)$validation['issues'][0]['severity_label'], 'issue severity label');
```

- [ ] **步骤 2：运行测试验证红灯**

预期：退出码非 0，报错包含 `Call to undefined method ...::detail()`。

- [ ] **步骤 3：实现展示装饰方法**

在展示服务中增加以下公开方法，所有方法只追加 `*_label` 和 `summary` 字段：

```php
public static function versions(array $versions): array
{
    foreach ($versions as &$version) {
        $version['status_label'] = self::label('version', (string)($version['status'] ?? ''));
    }
    unset($version);
    return $versions;
}

public static function detail(?array $detail): ?array
{
    if ($detail === null) {
        return null;
    }
    $detail['status_label'] = self::label('version', (string)($detail['status'] ?? ''));
    $responsibilityCount = 0;
    $namedPersonCount = 0;
    $runtimeCount = 0;
    foreach ($detail['activities'] as &$activity) {
        foreach ($activity['responsibilities'] as &$responsibility) {
            $responsibility['duty_type_label'] = self::label('duty', (string)$responsibility['duty_type']);
            $responsibility['assignment_mode_label'] = self::label('assignment_mode', (string)$responsibility['assignment_mode']);
            $roleCode = (string)($responsibility['activity_role_code'] ?? $responsibility['dynamic_owner_code'] ?? '');
            $responsibility['responsible_party_label'] = (string)($responsibility['fixed_position_name'] ?? '') !== ''
                ? (string)$responsibility['fixed_position_name']
                : self::label('role', $roleCode);
            $responsibility['source_refs_label'] = implode('、', array_map('strval', (array)($responsibility['source_refs'] ?? [])));
            foreach ($responsibility['assignments'] as &$assignment) {
                $assignment['status_label'] = self::label('assignment', (string)$assignment['status']);
            }
            unset($assignment);
            $responsibilityCount++;
            if ((string)$responsibility['assignment_mode'] === 'named_person') {
                $namedPersonCount++;
            } else {
                $runtimeCount++;
            }
        }
        unset($responsibility);
    }
    unset($activity);
    $detail['summary'] = [
        'activity_count' => count($detail['activities']),
        'responsibility_count' => $responsibilityCount,
        'named_person_count' => $namedPersonCount,
        'runtime_count' => $runtimeCount,
    ];
    return $detail;
}

public static function validation(?array $validation): ?array
{
    if ($validation === null) {
        return null;
    }
    $validation['result_label'] = self::label('validation', (string)($validation['result'] ?? ''));
    foreach ($validation['issues'] as &$issue) {
        $issue['severity_label'] = self::label('severity', (string)($issue['severity'] ?? ''));
    }
    unset($issue);
    return $validation;
}
```

增加以下装饰方法；不得删除原始字段，因为业务服务和技术详情仍会使用它们：

```php
public static function approvalHistory(array $rows): array
{
    foreach ($rows as &$row) {
        $row['decision_label'] = self::label('decision', (string)($row['decision'] ?? ''));
        $row['approver_position_label'] = self::label('position', (string)($row['approver_position_code'] ?? ''));
    }
    unset($row);
    return $rows;
}

public static function pendingBatch(?array $batch): ?array
{
    if ($batch === null) {
        return null;
    }
    $batch['approver']['position_label'] = self::label(
        'position',
        (string)($batch['approver']['position_code'] ?? '')
    );
    return $batch;
}

public static function effectiveAppointments(array $rows): array
{
    foreach ($rows as &$row) {
        $row['evidence_label'] = (string)($row['source_approval_id'] ?? '') === ''
            ? '待核对签批依据'
            : '签批依据完整';
    }
    unset($row);
    return $rows;
}

public static function alignment(array $alignment): array
{
    $findings = (array)($alignment['findings'] ?? []);
    foreach ($findings as &$finding) {
        $finding['status_label'] = self::label('alignment', (string)($finding['status'] ?? ''));
    }
    unset($finding);
    $alignment['findings'] = $findings;
    if (isset($alignment['version']['status'])) {
        $alignment['version']['status_label'] = self::label(
            'version',
            (string)$alignment['version']['status']
        );
    }
    return $alignment;
}
```

- [ ] **步骤 4：在控制器集中调用展示服务**

引入服务：

```php
use app\service\QmsResponsibilityPresentationService;
```

在 `render()` 中保持原始数据用于业务判断，另生成模板数据：

```php
$presentedVersions = QmsResponsibilityPresentationService::versions($versions);
$presentedDetail = QmsResponsibilityPresentationService::detail($detail);
$validationResult = QmsResponsibilityPresentationService::validation(
    Session::get('responsibility_validation')
);
```

`View::assign()` 中把 `versions`、`detail`、`validationResult` 替换成上述展示数据；对 `pendingBatch`、`approvalHistory`、`effectiveAppointments`、`alignmentData` 调用对应装饰方法。版本选择、有效状态判断和权限判断继续使用原始 `$versions`、`$detail`。

将 `validateVersion()` 的成功提示改为使用中文结果，不再拼接原始 `pass/warning/blocker`：

```php
$resultLabel = QmsResponsibilityPresentationService::label(
    'validation',
    (string)($result['result'] ?? '')
);
Session::flash(
    ($result['result'] ?? '') === 'pass' ? 'success' : 'warning',
    '责任链校验完成：' . $resultLabel . '，发现 ' . count($result['issues'] ?? []) . ' 项。'
);
```

- [ ] **步骤 5：运行展示测试和原责任链 UI 测试**

运行：

```bash
for test in tests/qms_responsibility_presentation_smoke.php tests/qms_responsibility_ui_smoke.php; do
  docker compose -f compose.yaml -f compose.responsibility-test.yaml -p "$PROJECT" \
    run --rm app php "$test"
done
```

预期：两项均退出 0。

- [ ] **步骤 6：提交展示数据装配**

```bash
git add jewelry-qms/app/service/QmsResponsibilityPresentationService.php \
  jewelry-qms/tests/qms_responsibility_presentation_smoke.php \
  jewelry-qms/app/controller/PlanningResponsibility.php
git commit -m "refactor(qms): 分离责任链业务数据与展示数据"
```

### 任务 3：重构页头、四步导航和责任结构

**文件：**
- 修改：`jewelry-qms/tests/qms_responsibility_ui_smoke.php:69-86,295-380`
- 修改：`jewelry-qms/app/view/planning_responsibility/index.html:1-86`

- [ ] **步骤 1：编写四步流程和中文主界面的失败测试**

在静态模板断言中加入：

```php
foreach (['定义职责', '配置人员', '校验并签批', '查看已生效责任链', '体系文件职责一致性检查'] as $label) {
    responsibility_ui_contains($label, $view, 'Responsibility workflow contains ' . $label);
}
foreach (['qms-responsibility-shell', 'qms-responsibility-steps', 'qms-responsibility-summary', '技术详情'] as $needle) {
    responsibility_ui_contains($needle, $view, 'Responsibility page contains progressive UI marker ' . $needle);
}
responsibility_ui_assert(!str_contains($view, '<strong>链编码：</strong>'), 'Chain code is not primary page text');
responsibility_ui_assert(!str_contains($view, '<th>环节</th>'), 'Internal step code is not a primary table column');
```

在已渲染的 `$structureHtml` 上加入：

```php
foreach (['草案', '活动', '职责', '需提前指定具体人员', '在具体活动中确定'] as $label) {
    responsibility_ui_contains($label, $structureHtml, 'Structure page renders ' . $label);
}
responsibility_ui_contains('<summary>技术详情</summary>', $structureHtml, 'Internal codes are progressively disclosed');
responsibility_ui_assert(!str_contains($structureHtml, '<strong>状态：</strong>draft'), 'Raw version status is not primary text');
```

- [ ] **步骤 2：运行责任链 UI 测试验证红灯**

预期：失败信息首先指向缺少 `定义职责` 或 `qms-responsibility-steps`。

- [ ] **步骤 3：实现页头和四步流程**

将页头包在 `<main class="qms-responsibility-shell">` 中。版本选择显示 `v{$version.version_no} · {$version.status_label}`。主流程链接固定为：

```html
<nav class="qms-responsibility-steps" aria-label="责任链办理进度">
    <a class="qms-responsibility-step {if $viewMode == 'structure'}is-active{/if}" href="/planning/responsibilities?view=structure&version_id={$versionId}">
        <span class="qms-responsibility-step-number">1</span><span><strong>定义职责</strong><small>确认活动、岗位和职责</small></span>
    </a>
    <a class="qms-responsibility-step {if $viewMode == 'staffing'}is-active{/if}" href="/planning/responsibilities?view=staffing&version_id={$versionId}">
        <span class="qms-responsibility-step-number">2</span><span><strong>配置人员</strong><small>为固定岗位指定人员</small></span>
    </a>
    <a class="qms-responsibility-step {if $viewMode == 'approval'}is-active{/if}" href="/planning/responsibilities?view=approval&version_id={$versionId}">
        <span class="qms-responsibility-step-number">3</span><span><strong>校验并签批</strong><small>检查条件并形成授权</small></span>
    </a>
    <a class="qms-responsibility-step {if $viewMode == 'effective'}is-active{/if}" href="/planning/responsibilities?view=effective&version_id={$versionId}">
        <span class="qms-responsibility-step-number">4</span><span><strong>查看已生效责任链</strong><small>核对任命与签批证据</small></span>
    </a>
</nav>
```

“体系文件职责一致性检查”放在页头次级链接，不作为第五步。

- [ ] **步骤 4：实现摘要与结构页渐进披露**

摘要展示版本中文状态、活动数、职责数和未绑定数量。结构表主列改为“职责类型、职责动作、责任岗位/角色、人员确定方式、配置状态”。每行加入：

```html
<details class="qms-responsibility-tech">
    <summary>技术详情</summary>
    <dl>
        <dt>环节代码</dt><dd><code>{$responsibility.step_code}</code></dd>
        <dt>人员确定方式</dt><dd><code>{$responsibility.assignment_mode}</code></dd>
        <dt>来源条款</dt><dd>{$responsibility.source_refs|default='未登记'}</dd>
    </dl>
</details>
```

主行使用 `{$responsibility.duty_type_label}` 和 `{$responsibility.assignment_mode_label}`；运行时职责主标签改为“在具体活动中确定”。保留 `data-runtime-slot` 和 `data-assignment-mode`，避免既有测试和运行边界退化。

- [ ] **步骤 5：运行 UI 测试验证绿灯**

预期：`qms_responsibility_ui_smoke passed`，并继续满足 9 个运行时职责、12 个固定人员职责的断言。

- [ ] **步骤 6：提交主流程与结构页**

```bash
git add jewelry-qms/app/view/planning_responsibility/index.html \
  jewelry-qms/tests/qms_responsibility_ui_smoke.php
git commit -m "feat(qms): 重构责任链四步流程与职责结构"
```

### 任务 4：优化人员配置、校验签批和有效证据

**文件：**
- 修改：`jewelry-qms/tests/qms_responsibility_ui_smoke.php:295-380,510-658`
- 修改：`jewelry-qms/app/view/planning_responsibility/index.html:88-198`

- [ ] **步骤 1：编写人员表单和技术信息折叠的失败测试**

加入静态和渲染断言：

```php
foreach (['具体人员', '适用场所', '拟生效日期', '能力证据', '资格证书'] as $label) {
    responsibility_ui_contains($label, $staffingHtml, 'Staffing form labels ' . $label);
}
responsibility_ui_contains('保存后仍是草案，不构成任命', $staffingHtml, 'Staffing consequence is explicit');

$approvalHtml = responsibility_ui_render_page($app, $versionId, 'approval');
foreach (['检查责任结构', '检查签批条件', '提交签批', '技术校验信息'] as $label) {
    responsibility_ui_contains($label, $approvalHtml, 'Approval page contains ' . $label);
}
responsibility_ui_assert(!str_contains($approvalHtml, '<p>内容哈希：'), 'Content hash is not primary approval text');

responsibility_ui_contains('证据详情', $view, 'Effective IDs have a progressive-disclosure entry');
$effectiveHtml = responsibility_ui_render_page($app, $versionId, 'effective');
responsibility_ui_assert(!str_contains($effectiveHtml, '<th>来源版本</th>'), 'Version UUID is not a primary table column');
```

- [ ] **步骤 2：运行 UI 测试验证红灯**

预期：失败信息指向缺少人员字段标签或“技术校验信息”。

- [ ] **步骤 3：重排人员配置表单**

对 `named_person` 职责使用带标签的响应式表单：

```html
<form method="post" action="/planning/responsibilities/assignments/save" class="qms-responsibility-staffing-form">
    <input type="hidden" name="operation" value="save">
    <input type="hidden" name="version_id" value="{$detail.id}">
    <input type="hidden" name="responsibility_id" value="{$responsibility.id}">
    <label><span>具体人员</span><select name="employee_id" class="form-select form-select-sm" required>...</select></label>
    <label><span>适用场所</span><select name="site_id" class="form-select form-select-sm">...</select></label>
    <label><span>拟生效日期</span><input name="proposed_from" type="date" class="form-control form-control-sm" required></label>
    <label><span>能力证据</span><select name="competency_record_id" class="form-select form-select-sm">...</select></label>
    <label><span>资格证书</span><select name="certificate_id" class="form-select form-select-sm">...</select></label>
    <div class="qms-responsibility-form-action"><button class="btn btn-sm btn-primary" type="submit">保存人员草案</button><small>保存后仍是草案，不构成任命。</small></div>
</form>
```

运行时职责只保留规则说明，不渲染表单。

- [ ] **步骤 4：重排校验、签批和治理身份区**

- 三个动作按“检查责任结构 → 检查签批条件 → 提交签批”排列，提交按钮是唯一主按钮。
- `validationResult.result_label` 与 `issue.severity_label` 作为主要文本。
- 内容哈希、版本哈希和批次键放入 `<details><summary>技术校验信息</summary>`。
- 公司总经理身份证据、实验室主任首次任命放入标题为“首次治理身份设置”的侧栏，并说明它不是日常配置。
- 签批记录使用 `decision_label` 与 `approver_position_label`。

- [ ] **步骤 5：简化已生效责任链主表**

主列保留“具体人员、岗位/角色、职责、场所、有效期、依据状态”。每行把 `source_chain_version_id`、`source_responsibility_id` 和 `source_approval_id` 放入：

```html
<details class="qms-responsibility-tech">
    <summary>证据详情</summary>
    <dl>
        <dt>责任链版本</dt><dd><code>{$appointment.source_chain_version_id}</code></dd>
        <dt>职责记录</dt><dd><code>{$appointment.source_responsibility_id|default='-'}</code></dd>
        <dt>签批记录</dt><dd><code>{$appointment.source_approval_id|default='-'}</code></dd>
    </dl>
</details>
```

- [ ] **步骤 6：运行 UI 测试验证绿灯**

预期：退出码 0；原有禁止自批、签批批次、事务和审计断言继续通过。

- [ ] **步骤 7：提交人员与签批体验优化**

```bash
git add jewelry-qms/app/view/planning_responsibility/index.html \
  jewelry-qms/tests/qms_responsibility_ui_smoke.php
git commit -m "feat(qms): 优化责任链人员配置与签批体验"
```

### 任务 5：优化体系文件职责一致性检查

**文件：**
- 修改：`jewelry-qms/tests/qms_responsibility_ui_smoke.php:578-658`
- 修改：`jewelry-qms/app/view/planning_responsibility/index.html:200-240`

- [ ] **步骤 1：编写业务化对齐结果的失败测试**

把原先要求 `baseline_hash` 直接出现的断言改为要求折叠证据，并增加：

```php
foreach (['体系文件职责一致性检查', '文件写法', '责任链写法', '核验依据'] as $label) {
    responsibility_ui_contains($label, $effectiveHtml, 'Alignment page contains business label ' . $label);
}
responsibility_ui_assert(!str_contains($effectiveHtml, '<span>baseline_hash：'), 'Baseline hash is not primary text');
responsibility_ui_assert(!str_contains($effectiveHtml, '<div class="small text-muted">conflict</div>'), 'Raw conflict status is not duplicated');
```

- [ ] **步骤 2：运行 UI 测试验证红灯**

预期：失败信息指向缺少“文件写法”或仍直接显示 `baseline_hash`。

- [ ] **步骤 3：实现业务主表与核验依据折叠**

- 页面标题改为“体系文件职责一致性检查”。
- 主要列为“发现项、结论、责任链要求、文件写法、处理提示”。
- 只显示中文 `status_label`，不重复英文 `status`。
- `content_hash`、`baseline_hash`、岗位代码、活动代码、职责 ID、来源条款和完整 JSON 统一放入 `<details><summary>核验依据</summary>`。
- 空状态改为“当前没有需要展示的一致性检查结果；请先选择责任链版本”。
- 草案提示保留“仅预览，不作为现行责任依据”。

- [ ] **步骤 4：运行 UI 测试验证绿灯**

预期：`qms_responsibility_ui_smoke passed`，且对齐页仍只读、不出现 `name="apply"`。

- [ ] **步骤 5：提交对齐页优化**

```bash
git add jewelry-qms/app/view/planning_responsibility/index.html \
  jewelry-qms/tests/qms_responsibility_ui_smoke.php
git commit -m "feat(qms): 业务化展示责任链文件一致性检查"
```

### 任务 6：增加模块专属样式并完成全量验证

**文件：**
- 创建：`jewelry-qms/public/static/css/qms-responsibility.css`
- 修改：`jewelry-qms/app/view/layout/main.html:8-11`
- 修改：`jewelry-qms/tests/qms_ui_navigation_template_smoke.php`
- 创建：`.team/交接箱/2026-07-14-责任链UI整治-v0.1/验收记录-v0.1.md`

- [ ] **步骤 1：编写样式加载与作用域失败测试**

在导航模板测试中加入：

```php
assert_true(
    str_contains($layoutView, '/static/css/qms-responsibility.css'),
    'Main layout should load scoped responsibility styles'
);
$responsibilityCss = (string)file_get_contents($root . '/public/static/css/qms-responsibility.css');
assert_true(
    str_contains($responsibilityCss, '.qms-responsibility-shell') &&
    str_contains($responsibilityCss, '.qms-responsibility-steps') &&
    str_contains($responsibilityCss, '@media'),
    'Responsibility styles should be scoped and responsive'
);
assert_true(
    preg_match('/(^|\})\s*(body|\.card|\.table|\.btn)\s*\{/m', $responsibilityCss) !== 1,
    'Responsibility stylesheet must not override global components without module scope'
);
```

- [ ] **步骤 2：运行导航测试验证红灯**

预期：失败信息包含 `Main layout should load scoped responsibility styles`。

- [ ] **步骤 3：加载专属样式文件**

在 `qms.css` 后增加：

```html
<link href="/static/css/qms-responsibility.css?v=20260714" rel="stylesheet">
```

- [ ] **步骤 4：实现专属样式**

样式文件至少包含以下规则，所有组件选择器都以 `.qms-responsibility-` 开头或由 `.qms-responsibility-shell` 约束：

```css
.qms-responsibility-shell { --rc-accent:#2c5282; --rc-soft:#eef4f8; --rc-line:#d8e2ea; }
.qms-responsibility-header { display:flex; gap:1rem; align-items:flex-start; justify-content:space-between; margin-bottom:1rem; }
.qms-responsibility-steps { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.75rem; margin-bottom:1rem; }
.qms-responsibility-step { display:flex; gap:.75rem; min-height:4.5rem; padding:.9rem; color:#41536a; background:#f7f9fb; border:1px solid var(--rc-line); border-radius:.55rem; text-decoration:none; transition:border-color .2s ease,background-color .2s ease,transform .2s ease; }
.qms-responsibility-step:hover { border-color:#8caac4; background:#f1f6fa; transform:translateY(-1px); }
.qms-responsibility-step:focus-visible { outline:3px solid rgba(44,82,130,.25); outline-offset:2px; }
.qms-responsibility-step.is-active { color:#183b5b; border-color:var(--rc-accent); background:var(--rc-soft); }
.qms-responsibility-step-number { display:grid; place-items:center; width:1.75rem; height:1.75rem; flex:0 0 auto; border-radius:.35rem; color:#fff; background:var(--rc-accent); font-variant-numeric:tabular-nums; }
.qms-responsibility-step small { display:block; margin-top:.15rem; color:#66788a; font-weight:400; }
.qms-responsibility-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.75rem; margin-bottom:1rem; }
.qms-responsibility-tech { margin-top:.5rem; color:#5f6f7f; }
.qms-responsibility-tech summary { cursor:pointer; color:#2c5282; font-weight:500; }
.qms-responsibility-tech dl { display:grid; grid-template-columns:max-content minmax(0,1fr); gap:.35rem .75rem; margin:.65rem 0 0; padding:.75rem; background:#f8fafc; border-radius:.4rem; }
.qms-responsibility-staffing-form { display:grid; grid-template-columns:repeat(5,minmax(9rem,1fr)); gap:.75rem; margin-top:1rem; }
.qms-responsibility-staffing-form label > span { display:block; margin-bottom:.3rem; color:#526579; font-size:.78rem; font-weight:600; }
.qms-responsibility-form-action { display:flex; align-items:center; gap:.75rem; grid-column:1/-1; }
@media (max-width: 991.98px) {
  .qms-responsibility-steps { display:flex; overflow-x:auto; padding-bottom:.25rem; }
  .qms-responsibility-step { min-width:15rem; }
  .qms-responsibility-summary { grid-template-columns:repeat(2,minmax(0,1fr)); }
  .qms-responsibility-staffing-form { grid-template-columns:repeat(2,minmax(0,1fr)); }
}
@media (max-width: 575.98px) {
  .qms-responsibility-header { display:block; }
  .qms-responsibility-summary,.qms-responsibility-staffing-form { grid-template-columns:1fr; }
}
```

- [ ] **步骤 5：运行样式与核心 UI 测试验证绿灯**

运行：

```bash
for test in \
  tests/qms_responsibility_presentation_smoke.php \
  tests/qms_responsibility_ui_smoke.php \
  tests/qms_ui_navigation_template_smoke.php \
  tests/rbac_controller_normalization_smoke.php; do
  docker compose -f compose.yaml -f compose.responsibility-test.yaml -p "$PROJECT" \
    run --rm app php "$test"
done
```

预期：4/4 退出 0。

- [ ] **步骤 6：运行责任链和相邻功能全量回归**

在全新 Compose 项目与空数据库卷中运行：

```bash
tests=(
  tests/qms_responsibility_schema_smoke.php
  tests/qms_responsibility_catalog_smoke.php
  tests/qms_responsibility_concurrency_smoke.php
  tests/qms_responsibility_draft_smoke.php
  tests/qms_responsibility_validation_smoke.php
  tests/qms_responsibility_approval_smoke.php
  tests/qms_responsibility_ui_smoke.php
  tests/qms_responsibility_alignment_smoke.php
  tests/qms_responsibility_end_to_end_smoke.php
  tests/qms_responsibility_presentation_smoke.php
  tests/qms_planning_ui_smoke.php
  tests/qms_manual_procedure_alignment_current_export_smoke.php
  tests/qms_ui_navigation_template_smoke.php
  tests/rbac_controller_normalization_smoke.php
)
for test in "${tests[@]}"; do
  docker compose -f compose.yaml -f compose.responsibility-test.yaml -p "$PROJECT" \
    run --rm app php "$test"
done
```

预期：14/14 退出 0；测试输出没有 PHP warning、模板异常或数据库残留错误。

- [ ] **步骤 7：运行静态检查和只读渲染检查**

```bash
php_files=(
  app/service/QmsResponsibilityPresentationService.php
  app/controller/PlanningResponsibility.php
  tests/qms_responsibility_presentation_smoke.php
  tests/qms_responsibility_ui_smoke.php
)
for file in "${php_files[@]}"; do
  docker compose -f compose.yaml -f compose.responsibility-test.yaml -p "$PROJECT" \
    run --rm app php -l "$file"
done
git diff --check
```

预期：每个文件输出 `No syntax errors detected`，`git diff --check` 无输出。

- [ ] **步骤 8：在隔离环境完成人工浏览器验收**

启动隔离应用并使用随机本机端口：

```bash
docker compose -f compose.yaml -f compose.responsibility-test.yaml -p "$PROJECT" up -d app
docker compose -f compose.yaml -f compose.responsibility-test.yaml -p "$PROJECT" port app 8000
```

用 `admin / password` 登录，检查桌面宽度和窄屏宽度下的：空状态、草案责任结构、人员配置、校验签批、有效责任链、草案文件对齐。确认主界面没有直接暴露英文状态、哈希字段名、UUID 或原始 JSON；技术详情仍可展开核对。

- [ ] **步骤 9：记录验收并提交样式与证据**

验收记录至少登记：红灯原因、绿灯输出、14 项测试、PHP lint、桌面/窄屏检查、数据库未变更、未连接 8010 环境。

```bash
git add jewelry-qms/public/static/css/qms-responsibility.css \
  jewelry-qms/app/view/layout/main.html \
  jewelry-qms/tests/qms_ui_navigation_template_smoke.php \
  .team/交接箱/2026-07-14-责任链UI整治-v0.1/验收记录-v0.1.md
git commit -m "test(qms): 完成责任链界面整治验收"
```

## 完成定义

- 设计规格中的 10 个章节均有对应任务。
- 所有展示映射均有自动测试。
- 主流程不再直接显示内部英文状态、哈希名称、UUID 和 JSON。
- 技术详情仍保留完整可追溯信息。
- 责任链 3 项活动、21 项职责、9 个运行时职责和 12 个固定人员职责不变。
- 禁止自批、资格校验、签批批次、事务、审计、RBAC 和文件对齐只读边界不变。
- 不修改数据库迁移、业务服务和其他模块页面。
- 隔离 Docker 项目已执行 `down -v --remove-orphans`，不残留容器、网络或数据卷。
