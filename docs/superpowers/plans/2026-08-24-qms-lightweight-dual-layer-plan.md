# QMS 轻量双层收口实现计划

> **面向 AI 代理的工作者：** 必需子技能：使用 superpowers:subagent-driven-development（推荐）或 superpowers:executing-plans 逐任务实现此计划。步骤使用复选框（`- [ ]`）语法来跟踪进度。

**目标：** 在不改变业务数据和 8010 的前提下，让 8021 日常层只显示“我的工作、查文件、查记录”，统一纸质运行文案，并把文件链路从常驻侧栏改为按需展开。

**架构：** 新增一个无数据库依赖的导航呈现服务，由认证中间件根据控制器、动作、查询参数和现有治理权限选择 `daily` 或 `governance`。模板只消费该上下文；文件数据仍由现有控制器和阅读服务提供。内部编号和内部状态只在呈现层转换，数据库值不修改。

**技术栈：** ThinkPHP 8、ThinkPHP 模板、PHP 8、Bootstrap 5、原生 CSS/JavaScript、现有 PHP smoke tests、Docker 8021 运行栈。

---

## 文件结构

- 创建 `jewelry-qms/app/service/NavigationPresentationService.php`：唯一负责日常/治理层选择、活动入口和统一边界提示。
- 修改 `jewelry-qms/app/middleware/Auth.php`：调用导航呈现服务并向视图分配 `qmsNavigation`。
- 修改 `jewelry-qms/app/view/layout/main.html`：按 `qmsNavigation.layer` 渲染轻量日常导航或完整治理导航。
- 创建 `jewelry-qms/public/static/css/qms-navigation.css`：导航层、统一运行提示、移动端折叠和内容宽度样式。
- 修改 `jewelry-qms/app/service/DocumentPresentationService.php`：提供员工熟悉的业务编号和日常阅读状态文案。
- 修改 `jewelry-qms/app/service/DocumentReadingService.php`：向阅读页提供 `display_number` 和稳定的日常状态。
- 修改 `jewelry-qms/app/controller/Document.php`：文件列表和最近阅读使用业务编号。
- 修改 `jewelry-qms/app/view/document/index.html`：删除重复状态横幅和普通层内部信息入口。
- 修改 `jewelry-qms/app/view/document/read.html`：隐藏内部状态，把链路改为原生可展开区域。
- 修改 `jewelry-qms/app/view/quality_workbench/index.html`：删除重复纸质边界横幅。
- 修改 `jewelry-qms/public/static/css/qms-document-reader.css`：阅读页改为两栏，链路横跨正文区域并响应式展开。
- 修改 `jewelry-qms/public/static/js/qms-document-reader.js`：通过锚点进入链路时自动展开，不承担基本可用性。
- 创建 `jewelry-qms/tests/qms_lightweight_dual_layer_ui_smoke.php`：锁定本轮新增行为。
- 修改现有 `qms_document_reader_ui_smoke.php` 与 `qms_quality_workbench_path_b_smoke.php`：更新旧三栏和旧文案断言。

### 任务 1：用失败测试锁定方案 A

**文件：**
- 创建：`jewelry-qms/tests/qms_lightweight_dual_layer_ui_smoke.php`
- 修改：`jewelry-qms/tests/qms_document_reader_ui_smoke.php`
- 修改：`jewelry-qms/tests/qms_quality_workbench_path_b_smoke.php`

- [ ] **步骤 1：创建轻量双层失败测试**

测试必须包含以下实际断言：

```php
<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\DocumentPresentationService;
use app\service\NavigationPresentationService;

(new think\App())->initialize();

function lightweight_ui_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$daily = NavigationPresentationService::context('Document', 'read', true, []);
$governance = NavigationPresentationService::context('QualityWorkbench', 'projects', true, []);
$history = NavigationPresentationService::context('Document', 'index', true, ['history' => '1']);
$staff = NavigationPresentationService::context('Capa', 'index', false, []);

lightweight_ui_assert($daily['layer'] === 'daily', '正文阅读必须使用日常层');
lightweight_ui_assert($governance['layer'] === 'governance', '评审项目必须使用治理层');
lightweight_ui_assert($history['layer'] === 'governance', '历史版本管理必须使用治理层');
lightweight_ui_assert($staff['layer'] === 'daily', '无治理权限用户不得得到治理导航');
lightweight_ui_assert($daily['can_govern'] === true, '有治理权限用户在日常层应获得进入质量管理入口');
lightweight_ui_assert(str_contains($daily['notice'], '纸质文件为正式依据'), '日常层必须使用唯一纸质边界文案');

lightweight_ui_assert(
    DocumentPresentationService::businessNumber('SIM-GOV03-XZTC/CX-08-2026') === 'CX-08',
    '普通页面必须把试装编号转换为业务编号'
);
lightweight_ui_assert(
    DocumentPresentationService::dailyStatusLabel('published') === '当前治理阅读版',
    '普通页面不得把测试库 published 显示成正式发布'
);

$layout = (string)file_get_contents(__DIR__ . '/../app/view/layout/main.html');
$index = (string)file_get_contents(__DIR__ . '/../app/view/document/index.html');
$reader = (string)file_get_contents(__DIR__ . '/../app/view/document/read.html');
$readerCss = (string)file_get_contents(__DIR__ . '/../public/static/css/qms-document-reader.css');
$navigationCss = (string)file_get_contents(__DIR__ . '/../public/static/css/qms-navigation.css');

foreach (['我的工作', '查文件', '查记录', '进入质量管理', '返回日常工作'] as $label) {
    lightweight_ui_assert(str_contains($layout, $label), '双层导航缺少：' . $label);
}
lightweight_ui_assert(str_contains($layout, "qmsNavigation.layer == 'daily'"), '布局必须按日常/治理层渲染');
lightweight_ui_assert(str_contains($layout, '纸质文件为正式依据 · 系统用于快速查阅与治理核对'), '布局缺少唯一运行依据提示');
lightweight_ui_assert(!str_contains($index, '8021 当前阅读版'), '文件库不得显示测试环境内部状态');
lightweight_ui_assert(!str_contains($reader, '试运行环境已发布登记'), '阅读页不得显示已发布登记');
lightweight_ui_assert(str_contains($reader, '<details class="qms-document-reader__relations"'), '链路必须使用无脚本可展开结构');
lightweight_ui_assert(str_contains($reader, '{$data.relationship_count}'), '链路入口必须显示关系数量');
lightweight_ui_assert(str_contains($readerCss, 'grid-template-columns: 210px minmax(0, 1fr)'), '阅读页必须改为目录加正文两栏');
lightweight_ui_assert(str_contains($navigationCss, '.qms-daily-boundary'), '缺少日常层统一提示样式');

echo "qms_lightweight_dual_layer_ui_smoke passed\n";
```

- [ ] **步骤 2：更新旧测试的预期**

把 `qms_document_reader_ui_smoke.php` 中“三栏、文件链路常驻侧栏、治理中 · 纸质执行”的断言改为“两栏、`details` 可展开链路、当前治理阅读版”。把 `qms_quality_workbench_path_b_smoke.php` 增加“工作台首页不再重复运行依据横幅”的断言。

- [ ] **步骤 3：运行测试并确认先失败**

运行：

```bash
docker run --rm \
  -v "$PWD:/workspace" \
  -v "/Users/lc.leixyz/Documents/AI工作台/01-项目代码/LIMS-zhj/jewelry-qms/vendor:/workspace/vendor:ro" \
  -w /workspace jewelry-qms-app \
  php tests/qms_lightweight_dual_layer_ui_smoke.php
```

预期：FAIL，报错包含 `Class "app\service\NavigationPresentationService" not found`。

- [ ] **步骤 4：提交测试**

```bash
git add jewelry-qms/tests/qms_lightweight_dual_layer_ui_smoke.php \
  jewelry-qms/tests/qms_document_reader_ui_smoke.php \
  jewelry-qms/tests/qms_quality_workbench_path_b_smoke.php
git commit -m "test(QMS): 锁定轻量双层界面契约"
```

### 任务 2：实现日常/治理导航层

**文件：**
- 创建：`jewelry-qms/app/service/NavigationPresentationService.php`
- 修改：`jewelry-qms/app/middleware/Auth.php`
- 修改：`jewelry-qms/app/view/layout/main.html`
- 创建：`jewelry-qms/public/static/css/qms-navigation.css`

- [ ] **步骤 1：实现纯导航上下文服务**

服务必须把 `qualityworkbench/index`、`document/index|read|candidatepreview|sourcedownload`、记录实例的 `index|create|edit|view|print|download*` 识别为日常层；有治理权限且进入评审项目、后台模块、文件历史或待签批列表时识别为治理层。返回值固定包含：

```php
[
    'layer' => 'daily' | 'governance',
    'active' => 'work' | 'documents' | 'records' | 'governance',
    'can_govern' => bool,
    'notice' => '纸质文件为正式依据 · 系统用于快速查阅与治理核对',
]
```

- [ ] **步骤 2：由认证中间件分配导航上下文**

在 `Auth::handle()` 中调用：

```php
$canGovern = ActionAuthorizationService::allows('qualityworkbench', 'govern');
$qmsNavigation = NavigationPresentationService::context(
    $request->controller(),
    $request->action(),
    $canGovern,
    [
        'history' => (string)$request->param('history', ''),
        'pending_for_me' => (string)$request->param('pending_for_me', ''),
    ]
);
```

并通过 `View::assign()` 传入模板。

- [ ] **步骤 3：在主布局渲染双层导航**

日常层只渲染三个入口和按权限出现的 `进入质量管理`；治理层显示 `质量管理` 品牌、`返回日常工作` 和现有后台菜单。日常层隐藏原环境警告横幅，改在导航下方显示唯一 `.qms-daily-boundary`。

同时给主内容容器增加层级类：

```html
<div class="qms-shell-content {if $qmsNavigation.layer == 'daily'}is-daily{else/}is-governance{/if}">
```

- [ ] **步骤 4：增加导航和内容宽度样式**

`qms-navigation.css` 必须包含键盘焦点、日常提示、活动入口、质量入口、治理返回入口和移动端折叠样式；不得引入新依赖。

- [ ] **步骤 5：运行轻量双层测试**

运行任务 1 的 Docker 命令。预期：仍可能因文件呈现和链路布局尚未实现而 FAIL，但导航服务相关断言通过。

- [ ] **步骤 6：提交导航层**

```bash
git add jewelry-qms/app/service/NavigationPresentationService.php \
  jewelry-qms/app/middleware/Auth.php \
  jewelry-qms/app/view/layout/main.html \
  jewelry-qms/public/static/css/qms-navigation.css
git commit -m "feat(QMS): 分离日常工作与质量治理导航"
```

### 任务 3：收口普通员工文件信息

**文件：**
- 修改：`jewelry-qms/app/service/DocumentPresentationService.php`
- 修改：`jewelry-qms/app/service/DocumentReadingService.php`
- 修改：`jewelry-qms/app/controller/Document.php`
- 修改：`jewelry-qms/app/view/document/index.html`
- 修改：`jewelry-qms/app/view/quality_workbench/index.html`

- [ ] **步骤 1：增加业务编号与日常状态转换**

`businessNumber()` 只在呈现层依次去除 `SIM-GOV03-`、机构前缀 `XZTC/` 和末尾四位年份；无法识别时返回原编号。`dailyStatusLabel()` 固定把非作废候选显示为 `当前治理阅读版`，作废显示为 `已作废 · 仅供追溯`。

- [ ] **步骤 2：列表、阅读和最近阅读使用呈现字段**

为文件列表及 `DocumentReadingService::presentDocument()` 增加 `display_number`、`daily_status_label`，最近阅读会话写入 `display_number`，不修改原 `doc_number`、`version`、`status`。

- [ ] **步骤 3：收口文件库动作与文案**

删除页面内重复的运行阶段横幅。普通列表不显示内部 `version`、`published` 或完整试装编号，只显示业务编号、文件类别和日常状态。普通动作保留正文、来源 Word、相关信息；文件信息、新建、签批和历史管理只在治理层出现。

没有结果时使用：

> 没有找到匹配文件。请检查编号，或改用文件名称中的关键词。

- [ ] **步骤 4：删除我的工作重复横幅**

移除 `quality_workbench/index.html` 内 `.qms-my-work__notice`，运行依据由主布局唯一显示。

- [ ] **步骤 5：运行轻量双层与路径 B 测试**

```bash
php tests/qms_lightweight_dual_layer_ui_smoke.php
php tests/qms_quality_workbench_path_b_smoke.php
```

预期：文件呈现和工作台断言通过；链路布局断言仍可能失败。

- [ ] **步骤 6：提交文件信息收口**

```bash
git add jewelry-qms/app/service/DocumentPresentationService.php \
  jewelry-qms/app/service/DocumentReadingService.php \
  jewelry-qms/app/controller/Document.php \
  jewelry-qms/app/view/document/index.html \
  jewelry-qms/app/view/quality_workbench/index.html
git commit -m "feat(QMS): 隐藏日常文件内部试装表达"
```

### 任务 4：把文件链路改为按需展开

**文件：**
- 修改：`jewelry-qms/app/view/document/read.html`
- 修改：`jewelry-qms/public/static/css/qms-document-reader.css`
- 修改：`jewelry-qms/public/static/js/qms-document-reader.js`

- [ ] **步骤 1：正文页改为目录加正文两栏**

将 `.qms-document-reader__layout` 设置为：

```css
grid-template-columns: 210px minmax(0, 1fr);
```

正文最大可读宽度保持约 65—80 个中文字符，宽屏不再为常驻链路侧栏预留 310px。

- [ ] **步骤 2：使用原生 details/summary 承载链路**

把原 `<aside>` 改为：

```html
<details class="qms-document-reader__relations" id="document-relations">
    <summary>
        <span>相关依据、表格和责任岗位</span>
        <small>{$data.relationship_count} 项直接关系 · 展开查看</small>
    </summary>
    <div class="qms-document-reader__relations-body">
        {if !empty($data.relation_groups)}
        {foreach $data.relation_groups as $group}
        <section class="qms-relation-group">
            <h2>{$group.title}</h2>
            {foreach $group.items as $item}
            <div class="qms-relation-item">
                <div class="qms-relation-item__top">
                    <span>{$item.relation_label}</span>
                    <span class="qms-relation-state {$item.state_class}">{$item.state_label}</span>
                </div>
                {foreach $item.targets as $target}
                <div class="qms-relation-target"><small>{$target.label}</small><strong>{$target.value}</strong></div>
                {/foreach}
            </div>
            {/foreach}
        </section>
        {/foreach}
        {else/}
        <div class="qms-side-empty">当前未建立直接关系，需由质量人员复核。</div>
        {/if}
    </div>
</details>
```

空链路文案固定为“当前未建立直接关系，需由质量人员复核”。完整链路入口仅对具有治理权限的用户显示。

- [ ] **步骤 3：支持锚点自动展开与移动端布局**

JavaScript 只在 URL hash 为 `#document-relations` 时设置 `details.open = true`；不移除浏览器原生交互。移动端目录和链路均进入正文流，正文占满宽度。

- [ ] **步骤 4：运行三个核心 UI 测试并确认通过**

```bash
php tests/qms_lightweight_dual_layer_ui_smoke.php
php tests/qms_document_reader_ui_smoke.php
php tests/qms_quality_workbench_path_b_smoke.php
```

预期：三个脚本均输出 `passed`。

- [ ] **步骤 5：提交正文优先布局**

```bash
git add jewelry-qms/app/view/document/read.html \
  jewelry-qms/public/static/css/qms-document-reader.css \
  jewelry-qms/public/static/js/qms-document-reader.js
git commit -m "feat(QMS): 将文件链路改为按需展开"
```

### 任务 5：回归、8021 浏览器验收与交接

**文件：**
- 创建：`.team/交接箱/2026-08-24-QMS轻量双层收口/实施与验收报告.md`
- 创建：`.team/交接箱/2026-08-24-QMS轻量双层收口/验证证据/*.png`

- [ ] **步骤 1：运行语法和差异检查**

对所有修改过的 PHP 文件执行 `php -l`，再运行 `git diff --check`。预期全部退出 0。

- [ ] **步骤 2：运行现有回归测试**

运行以下全部测试：

```text
qms_lightweight_dual_layer_ui_smoke.php
qms_document_reader_ui_smoke.php
qms_quality_workbench_path_b_smoke.php
qms_document_source_asset_smoke.php
qms_quality_workbench_smoke.php
qms_final_candidate_document_access_smoke.php
qms_ui_navigation_template_smoke.php
qms_p0_action_authorization_smoke.php
qms_record_operator_access_smoke.php
qms_wave1_s5_rbac_whitelist_smoke.php
rbac_controller_normalization_smoke.php
```

预期：全部输出 passed；P0 与记录填报员用例数量不减少。

- [ ] **步骤 3：集成到当前 8021 绑定分支并复跑运行态检查**

确认主工作区用户改动不与本分支冲突后，使用 fast-forward 合并。不得覆盖 `jewelry-qms/config/app.php` 和其它用户未提交文件。合并后在正在运行的 8021 容器中复跑核心测试。

- [ ] **步骤 4：浏览器抽验**

登录 8021，依次验证：

1. 我的工作只有三个日常入口和按权限显示的治理入口。
2. 文件库搜索 CX-08 只返回一条且不显示内部试装编号。
3. CX-08 阅读页显示业务编号、宽正文和收起的链路入口。
4. 点击链路后能看到已确认与待复核关系。
5. 进入评审项目后切换为治理导航，并能返回日常工作。
6. 来源 Word 下载仍触发下载。

- [ ] **步骤 5：检查数据未变化**

核对候选文件 65、来源资产链接 65、`trial_ready` 表单 104、真实记录实例 13；不得执行 migration 或数据更新。

- [ ] **步骤 6：编写交接报告并提交最终代码**

报告必须区分：代码实现、开发者自测、浏览器技术验收、真实员工用户验收。3 人无讲解测试继续标记为待用户执行。
