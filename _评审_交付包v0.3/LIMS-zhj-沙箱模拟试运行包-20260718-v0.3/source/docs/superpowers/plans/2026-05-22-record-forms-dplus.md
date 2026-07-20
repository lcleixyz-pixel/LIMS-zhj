# Record Forms D+ Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the first usable D+ record form workflow: controlled source attachments, structured field editing, HTML print preview, and PDF generation.

**Architecture:** Keep original record files under document control, then add a separate `record_form_templates` and `record_form_instances` layer for system-native forms. Render fill forms from JSON schema, render print pages from PHP/HTML templates, and generate PDFs from the rendered print URL with a headless browser script.

**Tech Stack:** ThinkPHP 8, MySQL, Bootstrap 5, PHP services, Node.js Playwright for PDF generation, existing `FileService` for uploaded source attachments.

---

## Scope

This plan implements the confirmed D+ direction from `docs/superpowers/specs/2026-05-22-record-forms-dplus-design.md`.

Included:

- Record form template list, create, edit, view, and preview.
- Record form instance create, fill, view, print preview, and PDF export.
- JSON field schema validation and rendering.
- First five sample template definitions:
  - `XZTC/BG-01-02` 人员培训记录表
  - `XZTC/BG-04-03` 仪器设备和标准物质期间核查记录表
  - `XZTC/BG-20-07` 现场检测能力审核记录表
  - `XZTC/BG-21-01` 管理评审计划表
  - `XZTC/BG-30-05` 内部质量监控记录表

Excluded:

- Online Word editing.
- Automatic conversion of every historical Word file.
- Importing filled historical records as structured instances.
- Approval workflow for generated record instances.

## File Structure

- `jewelry-qms/database/jewelry_qms.sql`: add table definitions for form templates and instances.
- `jewelry-qms/app/Model/RecordFormTemplate.php`: model for controlled structured templates.
- `jewelry-qms/app/Model/RecordFormInstance.php`: model for generated form records.
- `jewelry-qms/app/service/RecordFormSchemaService.php`: normalize, validate, and render field schema.
- `jewelry-qms/app/service/RecordFormPrintService.php`: render printable HTML from a template key and field values.
- `jewelry-qms/app/service/PdfRenderService.php`: call the Node Playwright renderer and save PDF output.
- `jewelry-qms/app/service/RecordFormFixtureService.php`: seed the five starter template schemas.
- `jewelry-qms/app/controller/RecordFormTemplate.php`: template CRUD and preview endpoints.
- `jewelry-qms/app/controller/RecordFormInstance.php`: fill, save, print, and export endpoints.
- `jewelry-qms/app/view/record_form_template/*.html`: management screens.
- `jewelry-qms/app/view/record_form_instance/*.html`: fill and detail screens.
- `jewelry-qms/app/record_form_print/*.php`: printable A4 templates.
- `jewelry-qms/scripts/render-record-pdf.mjs`: Playwright PDF renderer.
- `jewelry-qms/package.json`: Node script and Playwright dependency.
- `jewelry-qms/tests/record_forms_schema_smoke.php`: schema service smoke test.
- `jewelry-qms/tests/record_forms_print_smoke.php`: print rendering smoke test.
- `jewelry-qms/tests/record_forms_pdf_smoke.html`: static HTML input for PDF renderer check.

## Task 1: Schema Service And Smoke Test

**Files:**
- Create: `jewelry-qms/tests/record_forms_schema_smoke.php`
- Create: `jewelry-qms/app/service/RecordFormSchemaService.php`

- [ ] **Step 1: Write the failing schema smoke test**

Create `jewelry-qms/tests/record_forms_schema_smoke.php`:

```php
<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\RecordFormSchemaService;

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$schema = [
    [
        'key' => 'training_date',
        'label' => '培训日期',
        'type' => 'date',
        'required' => true,
    ],
    [
        'key' => 'attendees',
        'label' => '参训人员',
        'type' => 'repeatable_table',
        'columns' => [
            ['key' => 'name', 'label' => '姓名', 'type' => 'text', 'required' => true],
            ['key' => 'signature', 'label' => '签名', 'type' => 'signature', 'required' => false],
        ],
    ],
];

$normalized = RecordFormSchemaService::normalize($schema);
assert_same('training_date', $normalized[0]['key'], 'Keeps field key');
assert_same('date', $normalized[0]['type'], 'Keeps field type');
assert_same(true, $normalized[0]['required'], 'Keeps required flag');
assert_same('', $normalized[0]['default'], 'Adds default value');
assert_same('repeatable_table', $normalized[1]['type'], 'Keeps repeatable table type');
assert_same('姓名', $normalized[1]['columns'][0]['label'], 'Keeps nested column label');

$values = [
    'training_date' => '2026-05-22',
    'attendees' => [
        ['name' => '张三', 'signature' => '张三'],
    ],
];
$errors = RecordFormSchemaService::validateValues($normalized, $values);
assert_same([], $errors, 'Accepts valid values');

$badValues = [
    'training_date' => '',
    'attendees' => [
        ['name' => '', 'signature' => ''],
    ],
];
$badErrors = RecordFormSchemaService::validateValues($normalized, $badValues);
assert_same('培训日期不能为空', $badErrors['training_date'], 'Reports missing required date');
assert_same('参训人员第1行姓名不能为空', $badErrors['attendees.0.name'], 'Reports missing required table cell');

echo "record_forms_schema_smoke passed\n";
```

- [ ] **Step 2: Run the test and confirm the expected failure**

Run:

```bash
cd /Users/lc.leixyz/LIMS-zhj/jewelry-qms
php tests/record_forms_schema_smoke.php
```

Expected: failure with `Class "app\service\RecordFormSchemaService" not found`.

- [ ] **Step 3: Implement the schema service**

Create `jewelry-qms/app/service/RecordFormSchemaService.php`:

```php
<?php
declare(strict_types=1);

namespace app\service;

use InvalidArgumentException;

class RecordFormSchemaService
{
    private const TYPES = [
        'text',
        'textarea',
        'date',
        'number',
        'select',
        'checkbox',
        'person',
        'department',
        'signature',
        'repeatable_table',
    ];

    public static function normalize(array $schema): array
    {
        $fields = [];
        foreach ($schema as $field) {
            $fields[] = self::normalizeField($field);
        }

        return $fields;
    }

    public static function decode(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('字段配置不是有效 JSON');
        }

        return self::normalize($decoded);
    }

    public static function encode(array $schema): string
    {
        return json_encode(self::normalize($schema), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public static function validateValues(array $schema, array $values): array
    {
        $errors = [];
        foreach ($schema as $field) {
            $key = $field['key'];
            $value = $values[$key] ?? $field['default'];

            if (($field['required'] ?? false) && self::isBlank($value)) {
                $errors[$key] = $field['label'] . '不能为空';
            }

            if ($field['type'] === 'repeatable_table') {
                $rows = is_array($value) ? array_values($value) : [];
                foreach ($rows as $rowIndex => $row) {
                    foreach ($field['columns'] as $column) {
                        $cellValue = is_array($row) ? ($row[$column['key']] ?? '') : '';
                        if (($column['required'] ?? false) && self::isBlank($cellValue)) {
                            $errors[$key . '.' . $rowIndex . '.' . $column['key']] =
                                $field['label'] . '第' . ($rowIndex + 1) . '行' . $column['label'] . '不能为空';
                        }
                    }
                }
            }
        }

        return $errors;
    }

    private static function normalizeField(array $field): array
    {
        $key = trim((string)($field['key'] ?? ''));
        $label = trim((string)($field['label'] ?? ''));
        $type = trim((string)($field['type'] ?? 'text'));

        if ($key === '') {
            throw new InvalidArgumentException('字段 key 不能为空');
        }
        if ($label === '') {
            throw new InvalidArgumentException('字段 label 不能为空');
        }
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('不支持的字段类型：' . $type);
        }

        $normalized = [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'required' => (bool)($field['required'] ?? false),
            'default' => $field['default'] ?? '',
            'options' => array_values($field['options'] ?? []),
            'print_bind' => (string)($field['print_bind'] ?? $key),
            'validation' => $field['validation'] ?? [],
            'help_text' => (string)($field['help_text'] ?? ''),
        ];

        if ($type === 'repeatable_table') {
            $columns = [];
            foreach (($field['columns'] ?? []) as $column) {
                $columns[] = self::normalizeField($column);
            }
            $normalized['columns'] = $columns;
        }

        return $normalized;
    }

    private static function isBlank(mixed $value): bool
    {
        if (is_array($value)) {
            return count($value) === 0;
        }

        return trim((string)$value) === '';
    }
}
```

- [ ] **Step 4: Run the smoke test**

Run:

```bash
cd /Users/lc.leixyz/LIMS-zhj/jewelry-qms
php tests/record_forms_schema_smoke.php
```

Expected: `record_forms_schema_smoke passed`.

- [ ] **Step 5: Commit**

```bash
cd /Users/lc.leixyz/LIMS-zhj
git add jewelry-qms/tests/record_forms_schema_smoke.php jewelry-qms/app/service/RecordFormSchemaService.php
git commit -m "feat: add record form schema service"
```

## Task 2: Database Tables, Models, Routes, And Navigation

**Files:**
- Modify: `jewelry-qms/database/jewelry_qms.sql`
- Create: `jewelry-qms/app/Model/RecordFormTemplate.php`
- Create: `jewelry-qms/app/Model/RecordFormInstance.php`
- Modify: `jewelry-qms/route/app.php`
- Modify: `jewelry-qms/config/qms.php`
- Modify: `jewelry-qms/app/view/layout/main.html`

- [ ] **Step 1: Add table definitions**

Insert this SQL block in `jewelry-qms/database/jewelry_qms.sql` after the `file_uploads` table:

```sql
CREATE TABLE `record_form_templates` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `document_id` varchar(36) DEFAULT NULL COMMENT '受控原始附件对应documents.id',
  `doc_number` varchar(50) NOT NULL COMMENT '记录表格编号',
  `name` varchar(300) NOT NULL,
  `module` varchar(200) DEFAULT NULL,
  `source_file_path` varchar(500) DEFAULT NULL,
  `source_file_name` varchar(255) DEFAULT NULL,
  `print_template_key` varchar(100) NOT NULL,
  `field_schema` text NOT NULL,
  `version` varchar(20) DEFAULT 'A/0',
  `status` enum('draft','published','obsolete') DEFAULT 'draft',
  `publish` tinyint(1) DEFAULT 1,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `doc_number` (`doc_number`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `record_form_instances` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `template_id` varchar(36) NOT NULL,
  `doc_number` varchar(50) NOT NULL,
  `record_title` varchar(300) NOT NULL,
  `field_values` text NOT NULL,
  `status` enum('draft','generated','locked','voided') DEFAULT 'draft',
  `generated_html_path` varchar(500) DEFAULT NULL,
  `generated_pdf_path` varchar(500) DEFAULT NULL,
  `generated_pdf_name` varchar(255) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `template_id` (`template_id`),
  KEY `doc_number` (`doc_number`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 2: Create models**

Create `jewelry-qms/app/Model/RecordFormTemplate.php`:

```php
<?php
declare(strict_types=1);

namespace app\model;

class RecordFormTemplate extends BaseModel
{
    protected $name = 'record_form_templates';

    protected $displayField = 'name';

    public function document()
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function instances()
    {
        return $this->hasMany(RecordFormInstance::class, 'template_id');
    }
}
```

Create `jewelry-qms/app/Model/RecordFormInstance.php`:

```php
<?php
declare(strict_types=1);

namespace app\model;

class RecordFormInstance extends BaseModel
{
    protected $name = 'record_form_instances';

    protected $displayField = 'record_title';

    public function template()
    {
        return $this->belongsTo(RecordFormTemplate::class, 'template_id');
    }
}
```

- [ ] **Step 3: Add routes**

In `jewelry-qms/route/app.php`, add these explicit routes inside the authenticated group after the document routes:

```php
    Route::rule('record_form_template/index', 'RecordFormTemplate/index');
    Route::rule('record_form_template/add', 'RecordFormTemplate/add');
    Route::rule('record_form_template/edit', 'RecordFormTemplate/edit');
    Route::rule('record_form_template/view', 'RecordFormTemplate/view');
    Route::rule('record_form_template/delete', 'RecordFormTemplate/delete');
    Route::get('record_form_template/source', 'RecordFormTemplate/source');
    Route::get('record_form_template/preview', 'RecordFormTemplate/preview');
    Route::post('record_form_template/seedSamples', 'RecordFormTemplate/seedSamples');

    Route::rule('record_form_instance/index', 'RecordFormInstance/index');
    Route::rule('record_form_instance/create', 'RecordFormInstance/create');
    Route::rule('record_form_instance/edit', 'RecordFormInstance/edit');
    Route::get('record_form_instance/view', 'RecordFormInstance/view');
    Route::get('record_form_instance/print', 'RecordFormInstance/print');
    Route::get('record_form_instance/exportPdf', 'RecordFormInstance/exportPdf');
    Route::get('record_form_instance/downloadPdf', 'RecordFormInstance/downloadPdf');
```

- [ ] **Step 4: Add permissions**

In `jewelry-qms/config/qms.php`, add `record_form_template` and `record_form_instance` to each role that currently has `document` access:

```php
        'quality_manager' => [
            'dashboard', 'document', 'record_form_template', 'record_form_instance', 'approval', 'doc_category', 'doc_template',
            'audit_plan', 'audit_schedule', 'audit_finding', 'audit_checklist',
            'management_review', 'review_action', 'capa', 'nonconformity', 'complaint',
            'equipment', 'equipment_maintenance', 'calibration',
            'training', 'training_record', 'competency_record',
            'supplier', 'supplier_evaluation', 'import', 'notification',
            'department', 'employee', 'user',
        ],
        'auditor' => [
            'dashboard', 'document', 'record_form_template', 'record_form_instance', 'audit_plan', 'audit_schedule', 'audit_finding', 'audit_checklist',
            'capa', 'nonconformity', 'complaint', 'notification',
        ],
        'department_head' => [
            'dashboard', 'document', 'record_form_template', 'record_form_instance', 'capa', 'nonconformity', 'complaint',
            'equipment', 'equipment_maintenance', 'calibration',
            'training', 'training_record', 'competency_record', 'notification',
        ],
        'staff' => [
            'dashboard', 'document', 'record_form_template', 'record_form_instance', 'complaint', 'notification',
        ],
```

- [ ] **Step 5: Add navigation links**

In `jewelry-qms/app/view/layout/main.html`, add these items under the 文件控制 dropdown after `体系文件列表`:

```html
                        <li><a class="dropdown-item" href="/record_form_template/index">记录表格模板</a></li>
                        <li><a class="dropdown-item" href="/record_form_instance/index">记录填写记录</a></li>
```

- [ ] **Step 6: Run syntax checks**

Run:

```bash
cd /Users/lc.leixyz/LIMS-zhj/jewelry-qms
php -l app/Model/RecordFormTemplate.php
php -l app/Model/RecordFormInstance.php
php -l route/app.php
php -l config/qms.php
```

Expected: each command prints `No syntax errors detected`.

- [ ] **Step 7: Commit**

```bash
cd /Users/lc.leixyz/LIMS-zhj
git add jewelry-qms/database/jewelry_qms.sql jewelry-qms/app/Model/RecordFormTemplate.php jewelry-qms/app/Model/RecordFormInstance.php jewelry-qms/route/app.php jewelry-qms/config/qms.php jewelry-qms/app/view/layout/main.html
git commit -m "feat: add record form data model"
```

## Task 3: Print Rendering Service And First Printable Template

**Files:**
- Create: `jewelry-qms/tests/record_forms_print_smoke.php`
- Create: `jewelry-qms/app/service/RecordFormPrintService.php`
- Create: `jewelry-qms/app/record_form_print/training_record.php`

- [ ] **Step 1: Write the failing print smoke test**

Create `jewelry-qms/tests/record_forms_print_smoke.php`:

```php
<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\RecordFormPrintService;

$template = [
    'doc_number' => 'XZTC/BG-01-02',
    'name' => '人员培训记录表',
    'version' => 'A/0',
];
$values = [
    'training_date' => '2026-05-22',
    'training_topic' => '新版记录表格填写要求',
    'trainer' => '质量负责人',
    'attendees' => [
        ['name' => '张三', 'department' => '检测室', 'signature' => '张三'],
    ],
    'effect_evaluation' => '现场问答合格',
];

$html = RecordFormPrintService::render('training_record', $template, $values);

foreach (['人员培训记录表', 'XZTC/BG-01-02', '新版记录表格填写要求', '张三'] as $needle) {
    if (!str_contains($html, $needle)) {
        fwrite(STDERR, 'Rendered HTML missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

echo "record_forms_print_smoke passed\n";
```

- [ ] **Step 2: Run the test and confirm the expected failure**

Run:

```bash
cd /Users/lc.leixyz/LIMS-zhj/jewelry-qms
php tests/record_forms_print_smoke.php
```

Expected: failure with `Class "app\service\RecordFormPrintService" not found`.

- [ ] **Step 3: Implement print rendering service**

Create `jewelry-qms/app/service/RecordFormPrintService.php`:

```php
<?php
declare(strict_types=1);

namespace app\service;

use RuntimeException;

class RecordFormPrintService
{
    public static function render(string $templateKey, array $template, array $values): string
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '', $templateKey);
        $path = root_path() . 'app' . DIRECTORY_SEPARATOR . 'record_form_print' . DIRECTORY_SEPARATOR . $safeKey . '.php';
        if (!is_file($path)) {
            throw new RuntimeException('打印模板不存在：' . $safeKey);
        }

        ob_start();
        include $path;

        return (string)ob_get_clean();
    }

    public static function value(array $values, string $key, string $default = ''): string
    {
        $value = $values[$key] ?? $default;
        if (is_array($value)) {
            return $default;
        }

        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    public static function rows(array $values, string $key): array
    {
        $rows = $values[$key] ?? [];

        return is_array($rows) ? array_values($rows) : [];
    }

    public static function cell(array $row, string $key): string
    {
        return htmlspecialchars((string)($row[$key] ?? ''), ENT_QUOTES, 'UTF-8');
    }
}
```

- [ ] **Step 4: Add training record printable template**

Create `jewelry-qms/app/record_form_print/training_record.php`:

```php
<?php
use app\service\RecordFormPrintService as P;

$attendees = P::rows($values, 'attendees');
if ($attendees === []) {
    $attendees = [['name' => '', 'department' => '', 'signature' => '']];
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($template['name'] ?? '人员培训记录表', ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        @page { size: A4; margin: 18mm 16mm; }
        body { font-family: "Noto Sans CJK SC", "Microsoft YaHei", Arial, sans-serif; color: #111; font-size: 12px; }
        .title { text-align: center; font-size: 20px; font-weight: 700; margin-bottom: 12px; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #111; padding: 7px 8px; vertical-align: middle; word-break: break-word; }
        th { background: #f5f5f5; font-weight: 700; }
        .label { width: 18%; }
        .signature { height: 46px; }
        .footer { display: flex; justify-content: space-between; margin-top: 10px; font-size: 11px; }
    </style>
</head>
<body>
    <div class="title"><?= htmlspecialchars($template['name'] ?? '人员培训记录表', ENT_QUOTES, 'UTF-8') ?></div>
    <div class="meta">
        <div>编号：<?= htmlspecialchars($template['doc_number'] ?? 'XZTC/BG-01-02', ENT_QUOTES, 'UTF-8') ?></div>
        <div style="text-align:right">版本：<?= htmlspecialchars($template['version'] ?? 'A/0', ENT_QUOTES, 'UTF-8') ?></div>
    </div>
    <table>
        <tr>
            <th class="label">培训日期</th>
            <td><?= P::value($values, 'training_date') ?></td>
            <th class="label">培训讲师</th>
            <td><?= P::value($values, 'trainer') ?></td>
        </tr>
        <tr>
            <th>培训主题</th>
            <td colspan="3"><?= P::value($values, 'training_topic') ?></td>
        </tr>
        <tr>
            <th>培训内容</th>
            <td colspan="3" style="height:80px"><?= nl2br(P::value($values, 'training_content')) ?></td>
        </tr>
    </table>
    <table style="margin-top:10px">
        <tr>
            <th style="width:10%">序号</th>
            <th>姓名</th>
            <th>部门</th>
            <th>签名</th>
        </tr>
        <?php foreach ($attendees as $index => $row): ?>
        <tr>
            <td style="text-align:center"><?= $index + 1 ?></td>
            <td><?= P::cell($row, 'name') ?></td>
            <td><?= P::cell($row, 'department') ?></td>
            <td class="signature"><?= P::cell($row, 'signature') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <table style="margin-top:10px">
        <tr>
            <th class="label">效果评价</th>
            <td style="height:70px"><?= nl2br(P::value($values, 'effect_evaluation')) ?></td>
        </tr>
    </table>
    <div class="footer">
        <span>系统生成记录，仅用于打印归档</span>
        <span>生成日期：<?= date('Y-m-d') ?></span>
    </div>
</body>
</html>
```

- [ ] **Step 5: Run the print smoke test**

Run:

```bash
cd /Users/lc.leixyz/LIMS-zhj/jewelry-qms
php tests/record_forms_print_smoke.php
```

Expected: `record_forms_print_smoke passed`.

- [ ] **Step 6: Commit**

```bash
cd /Users/lc.leixyz/LIMS-zhj
git add jewelry-qms/tests/record_forms_print_smoke.php jewelry-qms/app/service/RecordFormPrintService.php jewelry-qms/app/record_form_print/training_record.php
git commit -m "feat: add record form print renderer"
```

## Task 4: Template Management Controller And Views

**Files:**
- Create: `jewelry-qms/app/controller/RecordFormTemplate.php`
- Create: `jewelry-qms/app/view/record_form_template/index.html`
- Create: `jewelry-qms/app/view/record_form_template/add.html`
- Create: `jewelry-qms/app/view/record_form_template/edit.html`
- Create: `jewelry-qms/app/view/record_form_template/view.html`
- Create: `jewelry-qms/app/view/record_form_template/preview.html`

- [ ] **Step 1: Create controller**

Create `jewelry-qms/app/controller/RecordFormTemplate.php`:

```php
<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Document;
use app\model\RecordFormTemplate as TemplateModel;
use app\service\FileService;
use app\service\RecordFormFixtureService;
use app\service\RecordFormPrintService;
use app\service\RecordFormSchemaService;
use think\exception\HttpException;
use think\facade\Session;
use think\facade\View;

class RecordFormTemplate extends BaseController
{
    public function index()
    {
        $query = TemplateModel::where('soft_delete', 0);
        if ($keyword = trim((string)$this->request->param('keyword', ''))) {
            $query->where(function ($q) use ($keyword) {
                $q->where('doc_number', 'like', '%' . $keyword . '%')
                    ->whereOr('name', 'like', '%' . $keyword . '%')
                    ->whereOr('module', 'like', '%' . $keyword . '%');
            });
        }
        if ($status = trim((string)$this->request->param('status', ''))) {
            $query->where('status', $status);
        }

        $items = $query->order('doc_number', 'asc')->paginate(20);
        View::assign('items', $items);
        View::assign('pages', $items->render());
        View::assign('filter', [
            'keyword' => $this->request->param('keyword', ''),
            'status' => $this->request->param('status', ''),
        ]);

        return View::fetch('record_form_template/index');
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $id = qms_uuid();
            $schema = RecordFormSchemaService::decode((string)($data['field_schema'] ?? '[]'));

            $record = new TemplateModel();
            $record->id = $id;
            $record->doc_number = trim((string)$data['doc_number']);
            $record->name = trim((string)$data['name']);
            $record->module = trim((string)($data['module'] ?? ''));
            $record->print_template_key = trim((string)$data['print_template_key']);
            $record->field_schema = RecordFormSchemaService::encode($schema);
            $record->version = trim((string)($data['version'] ?? 'A/0'));
            $record->status = $data['status'] ?? 'draft';

            if (!empty($_FILES['source_file']['name'])) {
                $upload = FileService::upload($_FILES['source_file'], 'record-form-sources', $id);
                if ($upload) {
                    $record->source_file_name = $upload['file_name'];
                    $record->source_file_path = $upload['file_path'];
                }
            }

            $record->save();
            Session::flash('success', '记录表格模板已创建');

            return redirect('/record_form_template/view?id=' . $id);
        }

        return View::fetch('record_form_template/add');
    }

    public function edit()
    {
        $record = $this->findTemplate();
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $schema = RecordFormSchemaService::decode((string)($data['field_schema'] ?? '[]'));
            $update = [
                'doc_number' => trim((string)$data['doc_number']),
                'name' => trim((string)$data['name']),
                'module' => trim((string)($data['module'] ?? '')),
                'print_template_key' => trim((string)$data['print_template_key']),
                'field_schema' => RecordFormSchemaService::encode($schema),
                'version' => trim((string)($data['version'] ?? 'A/0')),
                'status' => $data['status'] ?? 'draft',
            ];
            if (!empty($_FILES['source_file']['name'])) {
                $upload = FileService::upload($_FILES['source_file'], 'record-form-sources', $record->id);
                if ($upload) {
                    $update['source_file_name'] = $upload['file_name'];
                    $update['source_file_path'] = $upload['file_path'];
                }
            }
            $record->save($update);
            Session::flash('success', '记录表格模板已更新');

            return redirect('/record_form_template/view?id=' . $record->id);
        }

        View::assign('record', $record);

        return View::fetch('record_form_template/edit');
    }

    public function view()
    {
        $record = $this->findTemplate();
        View::assign('record', $record);
        View::assign('schema', RecordFormSchemaService::decode($record->field_schema));

        return View::fetch('record_form_template/view');
    }

    public function preview()
    {
        $record = $this->findTemplate();
        $schema = RecordFormSchemaService::decode($record->field_schema);
        $values = [];
        foreach ($schema as $field) {
            $values[$field['key']] = $field['type'] === 'repeatable_table'
                ? [array_fill_keys(array_column($field['columns'] ?? [], 'key'), '')]
                : ($field['default'] ?? '');
        }

        return RecordFormPrintService::render($record->print_template_key, $record->toArray(), $values);
    }

    public function source()
    {
        $record = $this->findTemplate();
        if (!$record->source_file_path) {
            throw new HttpException(404, '原始附件不存在');
        }
        FileService::download($record->source_file_path, $record->source_file_name ?: $record->name);
    }

    public function delete()
    {
        $record = $this->findTemplate();
        $record->soft_delete = 1;
        $record->save();
        Session::flash('success', '记录表格模板已删除');

        return redirect('/record_form_template/index');
    }

    public function seedSamples()
    {
        $count = RecordFormFixtureService::seed();
        Session::flash('success', '已写入样板模板 ' . $count . ' 条');

        return redirect('/record_form_template/index');
    }

    private function findTemplate(): TemplateModel
    {
        $id = $this->request->param('id');
        $record = TemplateModel::where('soft_delete', 0)->find($id);
        if (!$record) {
            throw new HttpException(404, '记录表格模板不存在');
        }

        return $record;
    }
}
```

- [ ] **Step 2: Create index view**

Create `jewelry-qms/app/view/record_form_template/index.html`:

```html
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">记录表格模板</h5>
        <div class="d-flex gap-2">
            <form method="post" action="/record_form_template/seedSamples">
                <button type="submit" class="btn btn-sm btn-outline-success" onclick="return confirm('确认写入5个样板模板？')">写入样板</button>
            </form>
            <a href="/record_form_template/add" class="btn btn-sm btn-primary">新增模板</a>
        </div>
    </div>
    <div class="card-body">
        <form method="get" class="row g-2 mb-3">
            <div class="col-md-5">
                <input type="text" name="keyword" value="{$filter.keyword|default=''}" class="form-control" placeholder="编号、名称、模块">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">全部状态</option>
                    <option value="draft" {if $filter.status == 'draft'}selected{/if}>草稿</option>
                    <option value="published" {if $filter.status == 'published'}selected{/if}>已发布</option>
                    <option value="obsolete" {if $filter.status == 'obsolete'}selected{/if}>已作废</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-primary w-100" type="submit">筛选</button>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead>
                    <tr>
                        <th>编号</th>
                        <th>名称</th>
                        <th>模块</th>
                        <th>版本</th>
                        <th>状态</th>
                        <th>打印模板</th>
                        <th width="220">操作</th>
                    </tr>
                </thead>
                <tbody>
                    {volist name="items" id="item"}
                    <tr>
                        <td>{$item.doc_number}</td>
                        <td>{$item.name}</td>
                        <td>{$item.module|default='-'}</td>
                        <td>{$item.version|default='A/0'}</td>
                        <td>{$item.status}</td>
                        <td>{$item.print_template_key}</td>
                        <td>
                            <a href="/record_form_template/view?id={$item.id}" class="btn btn-sm btn-outline-info">查看</a>
                            <a href="/record_form_template/edit?id={$item.id}" class="btn btn-sm btn-outline-primary">编辑</a>
                            <a href="/record_form_instance/create?template_id={$item.id}" class="btn btn-sm btn-outline-success">填写</a>
                        </td>
                    </tr>
                    {/volist}
                    {empty name="items"}
                    <tr><td colspan="7" class="text-center text-muted">暂无记录表格模板</td></tr>
                    {/empty}
                </tbody>
            </table>
        </div>
        {$pages|raw}
    </div>
</div>
```

- [ ] **Step 3: Create add/edit/view templates**

Create the exact `add.html`, `edit.html`, and `view.html` files below.

`jewelry-qms/app/view/record_form_template/add.html`:

```html
<div class="card">
    <div class="card-header"><h5 class="mb-0">新增记录表格模板</h5></div>
    <div class="card-body">
        <form method="post" action="/record_form_template/add" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label required">编号</label>
                    <input type="text" name="doc_number" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label required">名称</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">模块</label>
                    <input type="text" name="module" class="form-control">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">版本</label>
                    <input type="text" name="version" value="A/0" class="form-control">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">状态</label>
                    <select name="status" class="form-select">
                        <option value="draft">草稿</option>
                        <option value="published">已发布</option>
                        <option value="obsolete">已作废</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label required">打印模板键</label>
                    <input type="text" name="print_template_key" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">原始附件</label>
                    <input type="file" name="source_file" class="form-control">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label required">字段配置 JSON</label>
                <textarea name="field_schema" class="form-control font-monospace" rows="18" required>[]</textarea>
            </div>
            <div class="text-end">
                <a href="/record_form_template/index" class="btn btn-secondary">取消</a>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>
```

`jewelry-qms/app/view/record_form_template/edit.html`:

```html
<div class="card">
    <div class="card-header"><h5 class="mb-0">编辑记录表格模板</h5></div>
    <div class="card-body">
        <form method="post" action="/record_form_template/edit?id={$record.id}" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label required">编号</label>
                    <input type="text" name="doc_number" value="{$record.doc_number}" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label required">名称</label>
                    <input type="text" name="name" value="{$record.name}" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">模块</label>
                    <input type="text" name="module" value="{$record.module|default=''}" class="form-control">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">版本</label>
                    <input type="text" name="version" value="{$record.version|default='A/0'}" class="form-control">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">状态</label>
                    <select name="status" class="form-select">
                        <option value="draft" {if $record.status == 'draft'}selected{/if}>草稿</option>
                        <option value="published" {if $record.status == 'published'}selected{/if}>已发布</option>
                        <option value="obsolete" {if $record.status == 'obsolete'}selected{/if}>已作废</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label required">打印模板键</label>
                    <input type="text" name="print_template_key" value="{$record.print_template_key}" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">替换原始附件</label>
                    <input type="file" name="source_file" class="form-control">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label required">字段配置 JSON</label>
                <textarea name="field_schema" class="form-control font-monospace" rows="18" required>{$record.field_schema}</textarea>
            </div>
            <div class="text-end">
                <a href="/record_form_template/view?id={$record.id}" class="btn btn-secondary">取消</a>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>
```

Create `jewelry-qms/app/view/record_form_template/view.html`:

```html
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">记录表格模板 - {$record.doc_number}</h5>
        <div class="d-flex gap-1">
            <a href="/record_form_instance/create?template_id={$record.id}" class="btn btn-sm btn-outline-success">填写记录</a>
            <a href="/record_form_template/preview?id={$record.id}" target="_blank" class="btn btn-sm btn-outline-info">打印预览</a>
            {if $record.source_file_path}
            <a href="/record_form_template/source?id={$record.id}" class="btn btn-sm btn-outline-secondary">下载原始附件</a>
            {/if}
            <a href="/record_form_template/edit?id={$record.id}" class="btn btn-sm btn-outline-primary">编辑</a>
            <a href="/record_form_template/index" class="btn btn-sm btn-outline-secondary">返回</a>
        </div>
    </div>
    <div class="card-body">
        <table class="table table-bordered">
            <tr><th width="150">编号</th><td>{$record.doc_number}</td><th width="150">名称</th><td>{$record.name}</td></tr>
            <tr><th>模块</th><td>{$record.module|default='-'}</td><th>版本</th><td>{$record.version|default='A/0'}</td></tr>
            <tr><th>状态</th><td>{$record.status}</td><th>打印模板</th><td>{$record.print_template_key}</td></tr>
            <tr><th>原始附件</th><td colspan="3">{$record.source_file_name|default='-'}</td></tr>
        </table>
    </div>
</div>
<div class="card">
    <div class="card-header"><h6 class="mb-0">字段配置</h6></div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-striped">
            <thead><tr><th>字段键</th><th>标签</th><th>类型</th><th>必填</th><th>打印绑定</th></tr></thead>
            <tbody>
                {volist name="schema" id="field"}
                <tr>
                    <td>{$field.key}</td>
                    <td>{$field.label}</td>
                    <td>{$field.type}</td>
                    <td>{if $field.required}是{else}否{/if}</td>
                    <td>{$field.print_bind}</td>
                </tr>
                {/volist}
            </tbody>
        </table>
    </div>
</div>
```

- [ ] **Step 4: Run syntax checks**

Run:

```bash
cd /Users/lc.leixyz/LIMS-zhj/jewelry-qms
php -l app/controller/RecordFormTemplate.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 5: Commit**

```bash
cd /Users/lc.leixyz/LIMS-zhj
git add jewelry-qms/app/controller/RecordFormTemplate.php jewelry-qms/app/view/record_form_template
git commit -m "feat: add record form template screens"
```

## Task 5: Instance Fill Flow

**Files:**
- Create: `jewelry-qms/app/controller/RecordFormInstance.php`
- Create: `jewelry-qms/app/view/record_form_instance/index.html`
- Create: `jewelry-qms/app/view/record_form_instance/create.html`
- Create: `jewelry-qms/app/view/record_form_instance/edit.html`
- Create: `jewelry-qms/app/view/record_form_instance/view.html`

- [ ] **Step 1: Create instance controller**

Create `jewelry-qms/app/controller/RecordFormInstance.php`:

```php
<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\RecordFormInstance as InstanceModel;
use app\model\RecordFormTemplate as TemplateModel;
use app\service\FileService;
use app\service\PdfRenderService;
use app\service\RecordFormPrintService;
use app\service\RecordFormSchemaService;
use think\exception\HttpException;
use think\facade\Session;
use think\facade\View;

class RecordFormInstance extends BaseController
{
    public function index()
    {
        $query = InstanceModel::with('template');
        if ($keyword = trim((string)$this->request->param('keyword', ''))) {
            $query->where(function ($q) use ($keyword) {
                $q->where('doc_number', 'like', '%' . $keyword . '%')
                    ->whereOr('record_title', 'like', '%' . $keyword . '%');
            });
        }
        $items = $query->order('created', 'desc')->paginate(20);
        View::assign('items', $items);
        View::assign('pages', $items->render());
        View::assign('filter', ['keyword' => $this->request->param('keyword', '')]);

        return View::fetch('record_form_instance/index');
    }

    public function create()
    {
        $template = $this->findTemplate();
        $schema = RecordFormSchemaService::decode($template->field_schema);

        if ($this->request->isPost()) {
            $values = $this->collectValues($schema);
            $errors = RecordFormSchemaService::validateValues($schema, $values);
            if ($errors !== []) {
                View::assign('errors', $errors);
                View::assign('template', $template);
                View::assign('schema', $schema);
                View::assign('values', $values);

                return View::fetch('record_form_instance/create');
            }

            $record = InstanceModel::create([
                'id' => qms_uuid(),
                'template_id' => $template->id,
                'doc_number' => $template->doc_number,
                'record_title' => trim((string)$this->request->post('record_title', $template->name)),
                'field_values' => json_encode($values, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                'status' => 'draft',
            ]);
            Session::flash('success', '记录草稿已保存');

            return redirect('/record_form_instance/view?id=' . $record->id);
        }

        View::assign('template', $template);
        View::assign('schema', $schema);
        View::assign('values', $this->defaultValues($schema));
        View::assign('errors', []);

        return View::fetch('record_form_instance/create');
    }

    public function edit()
    {
        $record = $this->findInstance();
        if ($record->status === 'locked') {
            Session::flash('warning', '已归档记录不能编辑');

            return redirect('/record_form_instance/view?id=' . $record->id);
        }

        $template = TemplateModel::find($record->template_id);
        $schema = RecordFormSchemaService::decode($template->field_schema);

        if ($this->request->isPost()) {
            $values = $this->collectValues($schema);
            $errors = RecordFormSchemaService::validateValues($schema, $values);
            if ($errors === []) {
                $record->save([
                    'record_title' => trim((string)$this->request->post('record_title', $record->record_title)),
                    'field_values' => json_encode($values, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                    'status' => 'draft',
                ]);
                Session::flash('success', '记录已保存');

                return redirect('/record_form_instance/view?id=' . $record->id);
            }
            View::assign('errors', $errors);
            View::assign('values', $values);
        } else {
            View::assign('errors', []);
            View::assign('values', json_decode($record->field_values, true) ?: []);
        }

        View::assign('record', $record);
        View::assign('template', $template);
        View::assign('schema', $schema);

        return View::fetch('record_form_instance/edit');
    }

    public function view()
    {
        $record = $this->findInstance();
        $template = TemplateModel::find($record->template_id);
        View::assign('record', $record);
        View::assign('template', $template);
        View::assign('values', json_decode($record->field_values, true) ?: []);

        return View::fetch('record_form_instance/view');
    }

    public function print()
    {
        $record = $this->findInstance();
        $template = TemplateModel::find($record->template_id);
        $values = json_decode($record->field_values, true) ?: [];

        return RecordFormPrintService::render($template->print_template_key, $template->toArray(), $values);
    }

    public function exportPdf()
    {
        $record = $this->findInstance();
        $url = (string)$this->request->domain() . '/record_form_instance/print?id=' . $record->id;
        $pdf = PdfRenderService::renderUrl($url, $record->id, $record->record_title);
        $record->save([
            'generated_pdf_path' => $pdf['file_path'],
            'generated_pdf_name' => $pdf['file_name'],
            'status' => 'generated',
        ]);
        Session::flash('success', 'PDF 已生成');

        return redirect('/record_form_instance/view?id=' . $record->id);
    }

    public function downloadPdf()
    {
        $record = $this->findInstance();
        if (!$record->generated_pdf_path) {
            throw new HttpException(404, 'PDF 尚未生成');
        }
        FileService::download($record->generated_pdf_path, $record->generated_pdf_name ?: $record->record_title . '.pdf');
    }

    private function findTemplate(): TemplateModel
    {
        $id = $this->request->param('template_id');
        $template = TemplateModel::where('soft_delete', 0)->find($id);
        if (!$template) {
            throw new HttpException(404, '记录表格模板不存在');
        }

        return $template;
    }

    private function findInstance(): InstanceModel
    {
        $id = $this->request->param('id');
        $record = InstanceModel::find($id);
        if (!$record) {
            throw new HttpException(404, '记录不存在');
        }

        return $record;
    }

    private function defaultValues(array $schema): array
    {
        $values = [];
        foreach ($schema as $field) {
            $values[$field['key']] = $field['type'] === 'repeatable_table' ? [] : ($field['default'] ?? '');
        }

        return $values;
    }

    private function collectValues(array $schema): array
    {
        $posted = $this->request->post('fields/a', []);
        $values = [];
        foreach ($schema as $field) {
            $key = $field['key'];
            $values[$key] = $field['type'] === 'repeatable_table'
                ? array_values($posted[$key] ?? [])
                : ($posted[$key] ?? '');
        }

        return $values;
    }
}
```

- [ ] **Step 2: Create fill form partial inside create view**

Create `jewelry-qms/app/view/record_form_instance/create.html`:

```html
<div class="card">
    <div class="card-header"><h5 class="mb-0">填写记录 - {$template.name}</h5></div>
    <div class="card-body">
        <form method="post" action="/record_form_instance/create?template_id={$template.id}">
            <div class="mb-3">
                <label class="form-label required">记录标题</label>
                <input type="text" name="record_title" class="form-control" value="{$template.name}" required>
            </div>
            {volist name="schema" id="field"}
            <div class="mb-3">
                <label class="form-label {if $field.required}required{/if}">{$field.label}</label>
                {if $field.type == 'textarea'}
                <textarea name="fields[{$field.key}]" class="form-control" rows="4">{$values[$field.key]|default=$field.default}</textarea>
                {elseif $field.type == 'date'}
                <input type="date" name="fields[{$field.key}]" class="form-control" value="{$values[$field.key]|default=$field.default}">
                {elseif $field.type == 'number'}
                <input type="number" step="0.01" name="fields[{$field.key}]" class="form-control" value="{$values[$field.key]|default=$field.default}">
                {elseif $field.type == 'select'}
                <select name="fields[{$field.key}]" class="form-select">
                    {volist name="field.options" id="option"}
                    <option value="{$option}" {if isset($values[$field.key]) && $values[$field.key] == $option}selected{/if}>{$option}</option>
                    {/volist}
                </select>
                {elseif $field.type == 'repeatable_table'}
                <table class="table table-sm table-bordered">
                    <thead><tr>{volist name="field.columns" id="column"}<th>{$column.label}</th>{/volist}</tr></thead>
                    <tbody>
                        {for start="0" end="5" name="rowIndex"}
                        <tr>
                            {volist name="field.columns" id="column"}
                            <td><input type="text" name="fields[{$field.key}][{$rowIndex}][{$column.key}]" class="form-control form-control-sm"></td>
                            {/volist}
                        </tr>
                        {/for}
                    </tbody>
                </table>
                {else}
                <input type="text" name="fields[{$field.key}]" class="form-control" value="{$values[$field.key]|default=$field.default}">
                {/if}
                {if isset($errors[$field.key])}<div class="text-danger small mt-1">{$errors[$field.key]}</div>{/if}
            </div>
            {/volist}
            <div class="text-end">
                <a href="/record_form_template/view?id={$template.id}" class="btn btn-secondary">取消</a>
                <button type="submit" class="btn btn-primary">保存草稿</button>
            </div>
        </form>
    </div>
</div>
```

- [ ] **Step 3: Create remaining instance views**

Create `jewelry-qms/app/view/record_form_instance/index.html`:

```html
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">记录填写记录</h5>
        <a href="/record_form_template/index" class="btn btn-sm btn-primary">选择模板填写</a>
    </div>
    <div class="card-body">
        <form method="get" class="row g-2 mb-3">
            <div class="col-md-6">
                <input type="text" name="keyword" value="{$filter.keyword|default=''}" class="form-control" placeholder="记录标题或编号">
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-primary w-100" type="submit">筛选</button>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead>
                    <tr>
                        <th>记录标题</th>
                        <th>编号</th>
                        <th>状态</th>
                        <th>创建时间</th>
                        <th width="260">操作</th>
                    </tr>
                </thead>
                <tbody>
                    {volist name="items" id="item"}
                    <tr>
                        <td>{$item.record_title}</td>
                        <td>{$item.doc_number}</td>
                        <td>{$item.status}</td>
                        <td>{$item.created|default='-'}</td>
                        <td>
                            <a href="/record_form_instance/view?id={$item.id}" class="btn btn-sm btn-outline-info">查看</a>
                            <a href="/record_form_instance/edit?id={$item.id}" class="btn btn-sm btn-outline-primary">编辑</a>
                            <a href="/record_form_instance/print?id={$item.id}" target="_blank" class="btn btn-sm btn-outline-secondary">打印预览</a>
                            <a href="/record_form_instance/exportPdf?id={$item.id}" class="btn btn-sm btn-outline-success">生成PDF</a>
                        </td>
                    </tr>
                    {/volist}
                    {empty name="items"}
                    <tr><td colspan="5" class="text-center text-muted">暂无填写记录</td></tr>
                    {/empty}
                </tbody>
            </table>
        </div>
        {$pages|raw}
    </div>
</div>
```

Create `jewelry-qms/app/view/record_form_instance/edit.html`:

```html
<div class="card">
    <div class="card-header"><h5 class="mb-0">编辑记录 - {$record.record_title}</h5></div>
    <div class="card-body">
        <form method="post" action="/record_form_instance/edit?id={$record.id}">
            <div class="mb-3">
                <label class="form-label required">记录标题</label>
                <input type="text" name="record_title" class="form-control" value="{$record.record_title}" required>
            </div>
            {volist name="schema" id="field"}
            <div class="mb-3">
                <label class="form-label {if $field.required}required{/if}">{$field.label}</label>
                {if $field.type == 'textarea'}
                <textarea name="fields[{$field.key}]" class="form-control" rows="4">{$values[$field.key]|default=$field.default}</textarea>
                {elseif $field.type == 'date'}
                <input type="date" name="fields[{$field.key}]" class="form-control" value="{$values[$field.key]|default=$field.default}">
                {elseif $field.type == 'number'}
                <input type="number" step="0.01" name="fields[{$field.key}]" class="form-control" value="{$values[$field.key]|default=$field.default}">
                {elseif $field.type == 'select'}
                <select name="fields[{$field.key}]" class="form-select">
                    {volist name="field.options" id="option"}
                    <option value="{$option}" {if isset($values[$field.key]) && $values[$field.key] == $option}selected{/if}>{$option}</option>
                    {/volist}
                </select>
                {elseif $field.type == 'repeatable_table'}
                <table class="table table-sm table-bordered">
                    <thead><tr>{volist name="field.columns" id="column"}<th>{$column.label}</th>{/volist}</tr></thead>
                    <tbody>
                        {for start="0" end="5" name="rowIndex"}
                        <tr>
                            {volist name="field.columns" id="column"}
                            <td><input type="text" name="fields[{$field.key}][{$rowIndex}][{$column.key}]" class="form-control form-control-sm"></td>
                            {/volist}
                        </tr>
                        {/for}
                    </tbody>
                </table>
                {else}
                <input type="text" name="fields[{$field.key}]" class="form-control" value="{$values[$field.key]|default=$field.default}">
                {/if}
                {if isset($errors[$field.key])}<div class="text-danger small mt-1">{$errors[$field.key]}</div>{/if}
            </div>
            {/volist}
            <div class="text-end">
                <a href="/record_form_instance/view?id={$record.id}" class="btn btn-secondary">取消</a>
                <button type="submit" class="btn btn-primary">保存草稿</button>
            </div>
        </form>
    </div>
</div>
```

Create `jewelry-qms/app/view/record_form_instance/view.html`:

```html
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">记录详情 - {$record.record_title}</h5>
        <div class="d-flex gap-1">
            <a href="/record_form_instance/print?id={$record.id}" target="_blank" class="btn btn-sm btn-outline-info">打印预览</a>
            <a href="/record_form_instance/exportPdf?id={$record.id}" class="btn btn-sm btn-outline-success">生成PDF</a>
            {if $record.generated_pdf_path}
            <a href="/record_form_instance/downloadPdf?id={$record.id}" class="btn btn-sm btn-outline-primary">下载PDF</a>
            {/if}
            <a href="/record_form_instance/edit?id={$record.id}" class="btn btn-sm btn-outline-secondary">编辑</a>
            <a href="/record_form_instance/index" class="btn btn-sm btn-outline-secondary">返回</a>
        </div>
    </div>
    <div class="card-body">
        <table class="table table-bordered">
            <tr><th width="150">编号</th><td>{$record.doc_number}</td><th width="150">状态</th><td>{$record.status}</td></tr>
            <tr><th>模板</th><td>{$template.name}</td><th>创建时间</th><td>{$record.created|default='-'}</td></tr>
            <tr><th>PDF</th><td colspan="3">{$record.generated_pdf_name|default='未生成'}</td></tr>
        </table>
        <pre class="bg-light border rounded p-3">{$record.field_values}</pre>
    </div>
</div>
```

- [ ] **Step 4: Run syntax checks**

Run:

```bash
cd /Users/lc.leixyz/LIMS-zhj/jewelry-qms
php -l app/controller/RecordFormInstance.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 5: Commit**

```bash
cd /Users/lc.leixyz/LIMS-zhj
git add jewelry-qms/app/controller/RecordFormInstance.php jewelry-qms/app/view/record_form_instance
git commit -m "feat: add record form fill flow"
```

## Task 6: PDF Generation With Playwright

**Files:**
- Create: `jewelry-qms/package.json`
- Create: `jewelry-qms/scripts/render-record-pdf.mjs`
- Create: `jewelry-qms/tests/record_forms_pdf_smoke.html`
- Create: `jewelry-qms/app/service/PdfRenderService.php`

- [ ] **Step 1: Add Node package configuration**

Create `jewelry-qms/package.json`:

```json
{
  "name": "jewelry-qms-record-forms",
  "private": true,
  "type": "module",
  "scripts": {
    "pdf:render": "node scripts/render-record-pdf.mjs"
  },
  "dependencies": {
    "playwright": "^1.53.0"
  }
}
```

- [ ] **Step 2: Add PDF renderer script**

Create `jewelry-qms/scripts/render-record-pdf.mjs`:

```js
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';

const [, , inputUrl, outputPath] = process.argv;

if (!inputUrl || !outputPath) {
  console.error('Usage: node scripts/render-record-pdf.mjs <url-or-file> <output-path>');
  process.exit(2);
}

fs.mkdirSync(path.dirname(outputPath), { recursive: true });

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1240, height: 1754 } });

const target = inputUrl.startsWith('/') ? `file://${inputUrl}` : inputUrl;
await page.goto(target, { waitUntil: 'networkidle' });
await page.pdf({
  path: outputPath,
  format: 'A4',
  printBackground: true,
  margin: {
    top: '0mm',
    right: '0mm',
    bottom: '0mm',
    left: '0mm'
  }
});

await browser.close();
console.log(outputPath);
```

- [ ] **Step 3: Add static PDF smoke HTML**

Create `jewelry-qms/tests/record_forms_pdf_smoke.html`:

```html
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4; margin: 18mm 16mm; }
        body { font-family: Arial, sans-serif; }
        h1 { text-align: center; font-size: 20px; }
        table { width: 100%; border-collapse: collapse; }
        td { border: 1px solid #111; padding: 8px; }
    </style>
</head>
<body>
    <h1>PDF Smoke</h1>
    <table><tr><td>record form pdf renderer</td></tr></table>
</body>
</html>
```

- [ ] **Step 4: Install Node dependencies and run smoke render**

Run:

```bash
cd /Users/lc.leixyz/LIMS-zhj/jewelry-qms
npm install
npx playwright install chromium
npm run pdf:render -- "$(pwd)/tests/record_forms_pdf_smoke.html" "$(pwd)/runtime/record-form-smoke.pdf"
test -s runtime/record-form-smoke.pdf
```

Expected: `runtime/record-form-smoke.pdf` exists and has non-zero size.

- [ ] **Step 5: Add PHP PDF render service**

Create `jewelry-qms/app/service/PdfRenderService.php`:

```php
<?php
declare(strict_types=1);

namespace app\service;

use RuntimeException;

class PdfRenderService
{
    public static function renderUrl(string $url, string $recordId, string $title): array
    {
        $safeTitle = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $title);
        $relativeDir = 'uploads/record-form-pdf/' . $recordId;
        $absoluteDir = public_path() . $relativeDir . DIRECTORY_SEPARATOR;
        if (!is_dir($absoluteDir)) {
            mkdir($absoluteDir, 0755, true);
        }

        $fileName = $safeTitle . '_' . date('YmdHis') . '.pdf';
        $absolutePath = $absoluteDir . $fileName;
        $script = root_path() . 'scripts' . DIRECTORY_SEPARATOR . 'render-record-pdf.mjs';
        $command = sprintf(
            'cd %s && node %s %s %s 2>&1',
            escapeshellarg(root_path()),
            escapeshellarg($script),
            escapeshellarg($url),
            escapeshellarg($absolutePath)
        );

        exec($command, $output, $code);
        if ($code !== 0 || !is_file($absolutePath)) {
            throw new RuntimeException('PDF 生成失败：' . implode("\n", $output));
        }

        return [
            'file_name' => $fileName,
            'file_path' => $relativeDir . '/' . $fileName,
        ];
    }
}
```

- [ ] **Step 6: Run syntax check**

Run:

```bash
cd /Users/lc.leixyz/LIMS-zhj/jewelry-qms
php -l app/service/PdfRenderService.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 7: Commit**

```bash
cd /Users/lc.leixyz/LIMS-zhj
git add jewelry-qms/package.json jewelry-qms/package-lock.json jewelry-qms/scripts/render-record-pdf.mjs jewelry-qms/tests/record_forms_pdf_smoke.html jewelry-qms/app/service/PdfRenderService.php
git commit -m "feat: add record form pdf renderer"
```

## Task 7: Sample Template Fixtures

**Files:**
- Create: `jewelry-qms/app/service/RecordFormFixtureService.php`
- Create: `jewelry-qms/app/record_form_print/periodic_check.php`
- Create: `jewelry-qms/app/record_form_print/audit_checklist.php`
- Create: `jewelry-qms/app/record_form_print/management_review_plan.php`
- Create: `jewelry-qms/app/record_form_print/quality_control_record.php`

- [ ] **Step 1: Add fixture service**

Create `jewelry-qms/app/service/RecordFormFixtureService.php`:

```php
<?php
declare(strict_types=1);

namespace app\service;

use app\model\RecordFormTemplate;

class RecordFormFixtureService
{
    public static function seed(): int
    {
        $count = 0;
        foreach (self::templates() as $row) {
            $existing = RecordFormTemplate::where('doc_number', $row['doc_number'])
                ->where('soft_delete', 0)
                ->find();
            $row['field_schema'] = RecordFormSchemaService::encode($row['field_schema']);
            if ($existing) {
                $existing->save($row);
            } else {
                $row['id'] = qms_uuid();
                RecordFormTemplate::create($row);
            }
            $count++;
        }

        return $count;
    }

    public static function templates(): array
    {
        return [
            [
                'doc_number' => 'XZTC/BG-01-02',
                'name' => '人员培训记录表',
                'module' => '人员培训程序',
                'print_template_key' => 'training_record',
                'version' => 'A/0',
                'status' => 'published',
                'field_schema' => [
                    ['key' => 'training_date', 'label' => '培训日期', 'type' => 'date', 'required' => true],
                    ['key' => 'training_topic', 'label' => '培训主题', 'type' => 'text', 'required' => true],
                    ['key' => 'trainer', 'label' => '培训讲师', 'type' => 'text', 'required' => true],
                    ['key' => 'training_content', 'label' => '培训内容', 'type' => 'textarea', 'required' => false],
                    ['key' => 'attendees', 'label' => '参训人员', 'type' => 'repeatable_table', 'columns' => [
                        ['key' => 'name', 'label' => '姓名', 'type' => 'text', 'required' => true],
                        ['key' => 'department', 'label' => '部门', 'type' => 'text', 'required' => false],
                        ['key' => 'signature', 'label' => '签名', 'type' => 'signature', 'required' => false],
                    ]],
                    ['key' => 'effect_evaluation', 'label' => '效果评价', 'type' => 'textarea', 'required' => false],
                ],
            ],
            [
                'doc_number' => 'XZTC/BG-04-03',
                'name' => '仪器设备和标准物质期间核查记录表',
                'module' => '仪器设备和标准物质期间核查程序',
                'print_template_key' => 'periodic_check',
                'version' => 'A/0',
                'status' => 'published',
                'field_schema' => [
                    ['key' => 'equipment_name', 'label' => '设备或标准物质名称', 'type' => 'text', 'required' => true],
                    ['key' => 'equipment_code', 'label' => '编号', 'type' => 'text', 'required' => true],
                    ['key' => 'check_date', 'label' => '核查日期', 'type' => 'date', 'required' => true],
                    ['key' => 'check_items', 'label' => '核查项目', 'type' => 'repeatable_table', 'columns' => [
                        ['key' => 'item', 'label' => '项目', 'type' => 'text', 'required' => true],
                        ['key' => 'method', 'label' => '方法', 'type' => 'text', 'required' => false],
                        ['key' => 'result', 'label' => '结果', 'type' => 'text', 'required' => true],
                        ['key' => 'conclusion', 'label' => '结论', 'type' => 'select', 'options' => ['合格', '不合格'], 'required' => true],
                    ]],
                    ['key' => 'checker', 'label' => '核查人', 'type' => 'text', 'required' => true],
                ],
            ],
            [
                'doc_number' => 'XZTC/BG-20-07',
                'name' => '现场检测能力审核记录表',
                'module' => '内部管理体系审核程序',
                'print_template_key' => 'audit_checklist',
                'version' => 'A/0',
                'status' => 'published',
                'field_schema' => [
                    ['key' => 'audit_date', 'label' => '审核日期', 'type' => 'date', 'required' => true],
                    ['key' => 'audited_department', 'label' => '受审核部门', 'type' => 'text', 'required' => true],
                    ['key' => 'auditor', 'label' => '审核员', 'type' => 'text', 'required' => true],
                    ['key' => 'check_items', 'label' => '检查内容', 'type' => 'repeatable_table', 'columns' => [
                        ['key' => 'clause', 'label' => '条款', 'type' => 'text', 'required' => true],
                        ['key' => 'requirement', 'label' => '检查要求', 'type' => 'textarea', 'required' => true],
                        ['key' => 'evidence', 'label' => '审核证据', 'type' => 'textarea', 'required' => false],
                        ['key' => 'result', 'label' => '结果', 'type' => 'select', 'options' => ['符合', '不符合', '观察项'], 'required' => true],
                    ]],
                ],
            ],
            [
                'doc_number' => 'XZTC/BG-21-01',
                'name' => '管理评审计划表',
                'module' => '管理评审程序',
                'print_template_key' => 'management_review_plan',
                'version' => 'A/0',
                'status' => 'published',
                'field_schema' => [
                    ['key' => 'review_year', 'label' => '评审年度', 'type' => 'text', 'required' => true],
                    ['key' => 'meeting_date', 'label' => '会议日期', 'type' => 'date', 'required' => true],
                    ['key' => 'host', 'label' => '主持人', 'type' => 'text', 'required' => true],
                    ['key' => 'participants', 'label' => '参加人员', 'type' => 'textarea', 'required' => true],
                    ['key' => 'inputs', 'label' => '评审输入', 'type' => 'repeatable_table', 'columns' => [
                        ['key' => 'topic', 'label' => '输入主题', 'type' => 'text', 'required' => true],
                        ['key' => 'owner', 'label' => '责任人', 'type' => 'text', 'required' => false],
                        ['key' => 'material', 'label' => '资料要求', 'type' => 'textarea', 'required' => false],
                    ]],
                ],
            ],
            [
                'doc_number' => 'XZTC/BG-30-05',
                'name' => '内部质量监控记录表',
                'module' => '检测结果质量控制及能力验证程序',
                'print_template_key' => 'quality_control_record',
                'version' => 'A/0',
                'status' => 'published',
                'field_schema' => [
                    ['key' => 'monitor_date', 'label' => '监控日期', 'type' => 'date', 'required' => true],
                    ['key' => 'monitor_type', 'label' => '监控类型', 'type' => 'select', 'options' => ['留样再测', '人员比对', '设备比对', '标准物质核查', '能力验证'], 'required' => true],
                    ['key' => 'sample_info', 'label' => '样品或项目信息', 'type' => 'textarea', 'required' => true],
                    ['key' => 'results', 'label' => '监控结果', 'type' => 'repeatable_table', 'columns' => [
                        ['key' => 'item', 'label' => '项目', 'type' => 'text', 'required' => true],
                        ['key' => 'expected', 'label' => '预期或参考值', 'type' => 'text', 'required' => false],
                        ['key' => 'actual', 'label' => '实测结果', 'type' => 'text', 'required' => true],
                        ['key' => 'judgement', 'label' => '判定', 'type' => 'select', 'options' => ['满意', '可疑', '不满意'], 'required' => true],
                    ]],
                    ['key' => 'follow_up', 'label' => '后续措施', 'type' => 'textarea', 'required' => false],
                ],
            ],
        ];
    }
}
```

- [ ] **Step 2: Add four additional print templates**

Create `jewelry-qms/app/record_form_print/periodic_check.php`:

```php
<?php
use app\service\RecordFormPrintService as P;
$rows = P::rows($values, 'check_items');
if ($rows === []) {
    $rows = [['item' => '', 'method' => '', 'result' => '', 'conclusion' => '']];
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title>仪器设备和标准物质期间核查记录表</title>
    <style>
        @page { size: A4; margin: 18mm 16mm; }
        body { font-family: "Noto Sans CJK SC", "Microsoft YaHei", Arial, sans-serif; color: #111; font-size: 12px; }
        .title { text-align: center; font-size: 20px; font-weight: 700; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #111; padding: 7px 8px; vertical-align: middle; word-break: break-word; }
        th { background: #f5f5f5; font-weight: 700; }
    </style>
</head>
<body>
    <div class="title">仪器设备和标准物质期间核查记录表</div>
    <table>
        <tr><th>编号</th><td><?= htmlspecialchars($template['doc_number'] ?? 'XZTC/BG-04-03', ENT_QUOTES, 'UTF-8') ?></td><th>版本</th><td><?= htmlspecialchars($template['version'] ?? 'A/0', ENT_QUOTES, 'UTF-8') ?></td></tr>
        <tr><th>名称</th><td><?= P::value($values, 'equipment_name') ?></td><th>设备/标准物质编号</th><td><?= P::value($values, 'equipment_code') ?></td></tr>
        <tr><th>核查日期</th><td><?= P::value($values, 'check_date') ?></td><th>核查人</th><td><?= P::value($values, 'checker') ?></td></tr>
    </table>
    <table style="margin-top:10px">
        <tr><th style="width:8%">序号</th><th>核查项目</th><th>方法</th><th>结果</th><th>结论</th></tr>
        <?php foreach ($rows as $index => $row): ?>
        <tr><td><?= $index + 1 ?></td><td><?= P::cell($row, 'item') ?></td><td><?= P::cell($row, 'method') ?></td><td><?= P::cell($row, 'result') ?></td><td><?= P::cell($row, 'conclusion') ?></td></tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
```

Create `jewelry-qms/app/record_form_print/audit_checklist.php`:

```php
<?php
use app\service\RecordFormPrintService as P;
$rows = P::rows($values, 'check_items');
if ($rows === []) {
    $rows = [['clause' => '', 'requirement' => '', 'evidence' => '', 'result' => '']];
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title>现场检测能力审核记录表</title>
    <style>
        @page { size: A4; margin: 18mm 16mm; }
        body { font-family: "Noto Sans CJK SC", "Microsoft YaHei", Arial, sans-serif; color: #111; font-size: 12px; }
        .title { text-align: center; font-size: 20px; font-weight: 700; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #111; padding: 7px 8px; vertical-align: middle; word-break: break-word; }
        th { background: #f5f5f5; font-weight: 700; }
    </style>
</head>
<body>
    <div class="title">现场检测能力审核记录表</div>
    <table>
        <tr><th>编号</th><td><?= htmlspecialchars($template['doc_number'] ?? 'XZTC/BG-20-07', ENT_QUOTES, 'UTF-8') ?></td><th>版本</th><td><?= htmlspecialchars($template['version'] ?? 'A/0', ENT_QUOTES, 'UTF-8') ?></td></tr>
        <tr><th>审核日期</th><td><?= P::value($values, 'audit_date') ?></td><th>受审核部门</th><td><?= P::value($values, 'audited_department') ?></td></tr>
        <tr><th>审核员</th><td colspan="3"><?= P::value($values, 'auditor') ?></td></tr>
    </table>
    <table style="margin-top:10px">
        <tr><th style="width:12%">条款</th><th>检查要求</th><th>审核证据</th><th style="width:14%">结果</th></tr>
        <?php foreach ($rows as $row): ?>
        <tr><td><?= P::cell($row, 'clause') ?></td><td><?= nl2br(P::cell($row, 'requirement')) ?></td><td><?= nl2br(P::cell($row, 'evidence')) ?></td><td><?= P::cell($row, 'result') ?></td></tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
```

Create `jewelry-qms/app/record_form_print/management_review_plan.php`:

```php
<?php
use app\service\RecordFormPrintService as P;
$rows = P::rows($values, 'inputs');
if ($rows === []) {
    $rows = [['topic' => '', 'owner' => '', 'material' => '']];
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title>管理评审计划表</title>
    <style>
        @page { size: A4; margin: 18mm 16mm; }
        body { font-family: "Noto Sans CJK SC", "Microsoft YaHei", Arial, sans-serif; color: #111; font-size: 12px; }
        .title { text-align: center; font-size: 20px; font-weight: 700; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #111; padding: 7px 8px; vertical-align: middle; word-break: break-word; }
        th { background: #f5f5f5; font-weight: 700; }
    </style>
</head>
<body>
    <div class="title">管理评审计划表</div>
    <table>
        <tr><th>编号</th><td><?= htmlspecialchars($template['doc_number'] ?? 'XZTC/BG-21-01', ENT_QUOTES, 'UTF-8') ?></td><th>版本</th><td><?= htmlspecialchars($template['version'] ?? 'A/0', ENT_QUOTES, 'UTF-8') ?></td></tr>
        <tr><th>评审年度</th><td><?= P::value($values, 'review_year') ?></td><th>会议日期</th><td><?= P::value($values, 'meeting_date') ?></td></tr>
        <tr><th>主持人</th><td><?= P::value($values, 'host') ?></td><th>参加人员</th><td><?= nl2br(P::value($values, 'participants')) ?></td></tr>
    </table>
    <table style="margin-top:10px">
        <tr><th>输入主题</th><th style="width:18%">责任人</th><th>资料要求</th></tr>
        <?php foreach ($rows as $row): ?>
        <tr><td><?= P::cell($row, 'topic') ?></td><td><?= P::cell($row, 'owner') ?></td><td><?= nl2br(P::cell($row, 'material')) ?></td></tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
```

Create `jewelry-qms/app/record_form_print/quality_control_record.php`:

```php
<?php
use app\service\RecordFormPrintService as P;
$rows = P::rows($values, 'results');
if ($rows === []) {
    $rows = [['item' => '', 'expected' => '', 'actual' => '', 'judgement' => '']];
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title>内部质量监控记录表</title>
    <style>
        @page { size: A4; margin: 18mm 16mm; }
        body { font-family: "Noto Sans CJK SC", "Microsoft YaHei", Arial, sans-serif; color: #111; font-size: 12px; }
        .title { text-align: center; font-size: 20px; font-weight: 700; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #111; padding: 7px 8px; vertical-align: middle; word-break: break-word; }
        th { background: #f5f5f5; font-weight: 700; }
    </style>
</head>
<body>
    <div class="title">内部质量监控记录表</div>
    <table>
        <tr><th>编号</th><td><?= htmlspecialchars($template['doc_number'] ?? 'XZTC/BG-30-05', ENT_QUOTES, 'UTF-8') ?></td><th>版本</th><td><?= htmlspecialchars($template['version'] ?? 'A/0', ENT_QUOTES, 'UTF-8') ?></td></tr>
        <tr><th>监控日期</th><td><?= P::value($values, 'monitor_date') ?></td><th>监控类型</th><td><?= P::value($values, 'monitor_type') ?></td></tr>
        <tr><th>样品或项目信息</th><td colspan="3"><?= nl2br(P::value($values, 'sample_info')) ?></td></tr>
    </table>
    <table style="margin-top:10px">
        <tr><th>项目</th><th>预期或参考值</th><th>实测结果</th><th style="width:16%">判定</th></tr>
        <?php foreach ($rows as $row): ?>
        <tr><td><?= P::cell($row, 'item') ?></td><td><?= P::cell($row, 'expected') ?></td><td><?= P::cell($row, 'actual') ?></td><td><?= P::cell($row, 'judgement') ?></td></tr>
        <?php endforeach; ?>
    </table>
    <table style="margin-top:10px">
        <tr><th style="width:20%">后续措施</th><td><?= nl2br(P::value($values, 'follow_up')) ?></td></tr>
    </table>
</body>
</html>
```

- [ ] **Step 3: Run syntax checks**

Run:

```bash
cd /Users/lc.leixyz/LIMS-zhj/jewelry-qms
php -l app/service/RecordFormFixtureService.php
php -l app/record_form_print/periodic_check.php
php -l app/record_form_print/audit_checklist.php
php -l app/record_form_print/management_review_plan.php
php -l app/record_form_print/quality_control_record.php
php tests/record_forms_schema_smoke.php
php tests/record_forms_print_smoke.php
```

Expected: syntax checks pass and both smoke tests pass.

- [ ] **Step 4: Commit**

```bash
cd /Users/lc.leixyz/LIMS-zhj
git add jewelry-qms/app/service/RecordFormFixtureService.php jewelry-qms/app/record_form_print
git commit -m "feat: seed sample record form templates"
```

## Task 8: Browser Smoke Verification

**Files:**
- No source files required if previous tasks are complete.

- [ ] **Step 1: Start the application**

Run:

```bash
cd /Users/lc.leixyz/LIMS-zhj/jewelry-qms
php think run -p 8088
```

Expected: ThinkPHP development server listens on `http://127.0.0.1:8088`.

- [ ] **Step 2: Apply schema to local database**

Run the SQL added in Task 2 against the local `jewelry_qms` database:

```bash
mysql -uroot jewelry_qms < database/jewelry_qms.sql
```

Expected: tables `record_form_templates` and `record_form_instances` exist.

- [ ] **Step 3: Verify screens**

Open these paths in the browser after logging in:

```text
http://127.0.0.1:8088/record_form_template/index
http://127.0.0.1:8088/record_form_instance/index
```

Expected:

- The 文件控制 menu includes `记录表格模板` and `记录填写记录`.
- The template list page opens without errors.
- The instance list page opens without errors.

- [ ] **Step 4: Seed and fill one sample**

In the browser:

1. Open `记录表格模板`.
2. Click `写入样板`.
3. Open `XZTC/BG-01-02 人员培训记录表`.
4. Click `填写记录`.
5. Fill `培训日期`, `培训主题`, `培训讲师`, one attendee row, and `效果评价`.
6. Save the draft.
7. Open `打印预览`.
8. Click `生成PDF`.

Expected:

- Draft saves without validation errors.
- Print preview contains `人员培训记录表`, `XZTC/BG-01-02`, and the entered attendee name.
- PDF generation creates a downloadable PDF file.

- [ ] **Step 5: Commit verification-only notes if docs changed**

If verification leads to a small doc note, commit it:

```bash
cd /Users/lc.leixyz/LIMS-zhj
git add docs
git commit -m "docs: record form smoke verification notes"
```

If no files changed, do not create a commit.

## Self-Review Checklist

- Spec coverage: controlled source attachment is handled by `source_file_path`; structured field editing is handled by schema and instance controllers; print preview is handled by `RecordFormPrintService`; PDF export is handled by Playwright and `PdfRenderService`; five sample templates are seeded by `RecordFormFixtureService`.
- Placeholder scan: the plan contains concrete file paths, commands, status values, table names, class names, and sample schema fields.
- Type consistency: models use `RecordFormTemplate` and `RecordFormInstance`; controllers use the same table fields introduced in SQL; routes match controller method names.
- Scope control: online Word editing and full historical record migration stay outside this implementation.
