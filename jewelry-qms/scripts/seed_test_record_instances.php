<?php
declare(strict_types=1);

/**
 * 用测试内容填充全部样板记录模板，并跑通：保存 → 打印 HTML → PDF。
 * 用法：docker compose exec app php scripts/seed_test_record_instances.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new think\App();
$app->initialize();

use app\model\RecordFormInstance as InstanceModel;
use app\model\RecordFormTemplate as TemplateModel;
use app\service\PdfRenderService;
use app\service\RecordFormPrintService;
use app\service\RecordFormSchemaService;
use think\facade\Session;

Session::set('user', [
    'id' => '00000000-0000-0000-0000-000000000040',
    'username' => 'admin',
    'name' => '系统管理员',
    'role' => 'admin',
]);

function sampleScalar(array $field): string
{
    $key = (string)$field['key'];
    $type = (string)$field['type'];
    $label = (string)($field['label'] ?? $key);

    return match ($type) {
        'date' => date('Y-m-d'),
        'number' => '1',
        'select' => (string)(($field['options'][0] ?? '男')),
        'person' => '测试员-雷工',
        'department' => '检测部',
        'signature' => '测试员-雷工',
        'textarea' => '【测试】' . $label . '：用于链路验证的模拟内容，非受控记录。',
        default => match ($key) {
            'plan_year' => (string)date('Y'),
            'birth_month' => '1990-01',
            'phone' => '13800000000',
            'backup_phone' => '13900000000',
            'email' => 'test@example.com',
            'id_number' => '110101199001011234',
            'work_years' => '5',
            'estimated_cost' => '1200',
            'height' => '170cm',
            default => '【测试】' . $label,
        },
    };
}

function sampleRow(array $columns, int $index): array
{
    $row = [];
    foreach ($columns as $column) {
        $key = (string)$column['key'];
        $type = (string)($column['type'] ?? 'text');
        $label = (string)($column['label'] ?? $key);
        $row[$key] = match ($type) {
            'date' => date('Y-m-d', strtotime('+' . $index . ' day')),
            'number' => (string)(80 + $index),
            'select' => (string)(($column['options'][0] ?? '合格')),
            'person' => '学员' . ($index + 1),
            'department' => '检测部',
            'signature' => '学员' . ($index + 1),
            default => match ($key) {
                'name' => '学员' . ($index + 1),
                'department' => '检测部',
                'signature' => '学员' . ($index + 1),
                'training_time' => date('Y-m') . '-0' . ($index + 1),
                'training_content', 'content', 'training_main_content' => '【测试】培训内容' . ($index + 1),
                'training_target' => '全体检测人员',
                'training_department' => '检测部',
                'certificate_type' => '上岗证',
                'certificate_number' => 'CERT-TEST-00' . ($index + 1),
                'issuer' => '本机构',
                'assessment_project' => '宝石鉴定基础',
                'oral_result', 'operation_result', 'result' => '合格',
                'written_score', 'assessment_score' => (string)(85 + $index),
                'host_department' => '技术部',
                'completion_status' => '已完成',
                'confirmation_method' => '实操考核',
                'period' => '2010-2014',
                'school' => '测试大学',
                'major' => '宝石学',
                'company' => '测试珠宝公司',
                'position' => '检测员',
                'leave_time' => '2020-01',
                'witness' => '证明人甲',
                'relationship' => '配偶',
                'work_unit' => '测试单位',
                'phone' => '1370000000' . $index,
                'remarks' => '测试备注',
                default => '【测试】' . $label . ($index + 1),
            },
        };
    }

    return $row;
}

function buildTestValues(array $schema): array
{
    $values = [];
    foreach ($schema as $field) {
        $key = (string)$field['key'];
        if (($field['type'] ?? '') === 'repeatable_table') {
            $columns = $field['columns'] ?? [];
            $values[$key] = [
                sampleRow($columns, 0),
                sampleRow($columns, 1),
            ];
            continue;
        }
        $values[$key] = sampleScalar($field);
    }

    return $values;
}

function renderPrintHtml(InstanceModel $record): string
{
    $template = [
        'id' => (string)$record->template_id,
        'doc_number' => (string)$record->doc_number,
        'name' => (string)($record->template_name ?: $record->record_title),
        'module' => (string)($record->template_module ?: ''),
        'version' => (string)($record->template_version ?: ''),
        'print_template_key' => (string)$record->template_print_template_key,
        'field_schema' => (string)$record->template_field_schema,
    ];
    $values = json_decode((string)$record->field_values, true);
    if (!is_array($values)) {
        throw new RuntimeException('field_values 无效');
    }

    return RecordFormPrintService::render((string)$template['print_template_key'], $template, $values);
}

$templates = TemplateModel::where('soft_delete', 0)->order('doc_number')->select();
$batchTag = 'TEST-LINK-' . date('Ymd-His');
$results = [];

foreach ($templates as $template) {
    $doc = (string)$template->doc_number;
    $name = (string)$template->name;
    $row = [
        'doc_number' => $doc,
        'name' => $name,
        'instance_id' => '',
        'print_ok' => false,
        'pdf_ok' => false,
        'pdf_path' => '',
        'pdf_bytes' => 0,
        'view_ok' => false,
        'error' => '',
    ];

    try {
        $schema = RecordFormSchemaService::decode((string)$template->field_schema);
        $values = buildTestValues($schema);
        $errors = RecordFormSchemaService::validateValues($schema, $values);
        if ($errors !== []) {
            throw new RuntimeException('校验失败：' . json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        $instance = InstanceModel::create([
            'template_id' => $template->id,
            'template_name' => $template->name,
            'template_module' => $template->module,
            'template_version' => $template->version,
            'template_print_template_key' => $template->print_template_key,
            'template_field_schema' => $template->field_schema,
            'doc_number' => $template->doc_number,
            'record_title' => $batchTag . '-' . $doc . '-' . $name,
            'field_values' => json_encode($values, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'status' => 'draft',
        ]);
        $row['instance_id'] = (string)$instance->id;

        $html = renderPrintHtml($instance);
        if (trim($html) === '' || str_contains($html, '系统发生错误')) {
            throw new RuntimeException('打印 HTML 异常');
        }
        $row['print_ok'] = true;

        $pdf = PdfRenderService::renderHtml($html, (string)$instance->id, (string)$instance->record_title);
        $abs = rtrim(public_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pdf['file_path']);
        if (!is_file($abs) || filesize($abs) < 1000) {
            throw new RuntimeException('PDF 文件无效：' . $pdf['file_path']);
        }
        $instance->save([
            'generated_pdf_path' => $pdf['file_path'],
            'generated_pdf_name' => $pdf['file_name'],
            'status' => 'generated',
        ]);
        $row['pdf_ok'] = true;
        $row['pdf_path'] = $pdf['file_path'];
        $row['pdf_bytes'] = (int)filesize($abs);

        // 轻量复核：重新读库并再渲染一次打印
        $reloaded = InstanceModel::where('id', $instance->id)->find();
        $html2 = renderPrintHtml($reloaded);
        $row['view_ok'] = trim($html2) !== '';
    } catch (Throwable $e) {
        $row['error'] = $e->getMessage();
    }

    $results[] = $row;
    $status = $row['error'] === '' ? 'OK' : 'FAIL';
    echo sprintf(
        "[%s] %s %s print=%s pdf=%s(%dB) id=%s%s\n",
        $status,
        $doc,
        $name,
        $row['print_ok'] ? 'Y' : 'N',
        $row['pdf_ok'] ? 'Y' : 'N',
        $row['pdf_bytes'],
        $row['instance_id'],
        $row['error'] === '' ? '' : ' err=' . $row['error']
    );
}

$ok = count(array_filter($results, static fn(array $r): bool => $r['error'] === ''));
$total = count($results);
$report = [
    'batch_tag' => $batchTag,
    'generated_at' => date('c'),
    'ok' => $ok,
    'total' => $total,
    'results' => $results,
];

$reportDir = root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'record-form-test-link';
if (!is_dir($reportDir)) {
    mkdir($reportDir, 0755, true);
}
$reportPath = $reportDir . DIRECTORY_SEPARATOR . $batchTag . '.json';
file_put_contents($reportPath, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo "\nSUMMARY ok={$ok}/{$total} report={$reportPath}\n";
exit($ok === $total ? 0 : 1);
