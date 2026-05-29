<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\QmsDocumentStructureService;
use app\service\QmsElementService;
use think\facade\Config;
use think\facade\Db;

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

QmsElementService::seedAll();
QmsDocumentStructureService::seedAll();

$template = Db::name('record_form_templates')
    ->alias('r')
    ->join('qms_elements e', 'e.id = r.element_id')
    ->where('r.doc_number', 'XZTC/BG-26-01')
    ->where('r.soft_delete', 0)
    ->field('r.id,r.doc_number,r.name,r.module,r.version,r.print_template_key,r.field_schema,r.element_id,e.name element_name')
    ->find();

assert_true((bool)$template, 'Computer software record template exists with an element');

$instanceId = 'smoke-runtime-evidence-2601';
$recordTitle = '运行证据 smoke：计算机软件登记记录';
$now = date('Y-m-d H:i:s');

try {
    Db::name('record_form_instances')->where('id', $instanceId)->delete();
    Db::name('record_form_instances')->insert([
        'id' => $instanceId,
        'company_id' => (string)Config::get('qms.company_id'),
        'template_id' => (string)$template['id'],
        'template_name' => (string)$template['name'],
        'template_module' => (string)$template['module'],
        'template_version' => (string)$template['version'],
        'template_print_template_key' => (string)$template['print_template_key'],
        'template_field_schema' => (string)$template['field_schema'],
        'doc_number' => (string)$template['doc_number'],
        'record_title' => $recordTitle,
        'field_values' => '{"software_items":[]}',
        'status' => 'generated',
        'created' => $now,
        'modified' => $now,
    ]);

    $detail = QmsElementService::elementDetail((string)$template['element_id']);
    $runtimeEvidence = $detail['runtime_evidence'] ?? [];
    assert_true(count($runtimeEvidence) >= 1, 'Element detail exposes runtime evidence rows');

    $evidence = null;
    foreach ($runtimeEvidence as $row) {
        if ((string)($row['id'] ?? '') === $instanceId) {
            $evidence = $row;
            break;
        }
    }

    assert_true(is_array($evidence), 'Element runtime evidence includes the filled record instance');
    assert_true((string)$evidence['record_title'] === $recordTitle, 'Runtime evidence keeps the record title');
    assert_true((string)$evidence['template_number'] === 'XZTC/BG-26-01', 'Runtime evidence names the record form template');
    assert_true((string)$evidence['template_name'] === (string)$template['name'], 'Runtime evidence keeps the template name');
    assert_true((string)$evidence['element_name'] === (string)$template['element_name'], 'Runtime evidence names the owning element');
    assert_true((string)$evidence['status'] === 'generated', 'Runtime evidence keeps the record status');
    assert_true((string)$evidence['instance_url'] === '/record_form_instance/view?id=' . $instanceId, 'Runtime evidence links to the filled record');
    assert_true((string)$evidence['template_url'] === '/record_form_template/view?id=' . (string)$template['id'], 'Runtime evidence links to the template');

    $row = null;
    foreach (QmsElementService::traceabilityMatrix() as $candidate) {
        if ((string)$candidate['element']->id === (string)$template['element_id']) {
            $row = $candidate;
            break;
        }
    }

    assert_true(is_array($row), 'Traceability matrix includes the runtime evidence element');
    assert_true((int)($row['runtime_evidence_count'] ?? 0) >= 1, 'Traceability matrix counts running evidence');

    $elementView = file_get_contents(dirname(__DIR__) . '/app/view/planning_element/view.html') ?: '';
    assert_contains('运行证据', $elementView, 'Element detail page shows runtime evidence section');
    assert_contains('runtime_evidence', $elementView, 'Element detail page renders runtime evidence rows');
    assert_contains('instance_url', $elementView, 'Element detail page links to filled record instances');

    $traceabilityView = file_get_contents(dirname(__DIR__) . '/app/view/planning_traceability/index.html') ?: '';
    assert_contains('运行证据', $traceabilityView, 'Traceability matrix exposes runtime evidence count');
    assert_contains('runtime_evidence_count', $traceabilityView, 'Traceability matrix renders runtime evidence count');
} finally {
    Db::name('record_form_instances')->where('id', $instanceId)->delete();
}

echo "qms_runtime_evidence_traceability_smoke passed\n";
