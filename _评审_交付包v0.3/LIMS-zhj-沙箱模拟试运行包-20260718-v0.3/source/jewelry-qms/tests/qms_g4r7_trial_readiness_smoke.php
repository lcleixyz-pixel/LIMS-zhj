<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
require $root . '/app/common.php';

$app = new think\App();
$app->initialize();

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

function assert_not_contains(string $needle, string $haystack, string $message): void
{
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Unexpected: ' . $needle . PHP_EOL);
        exit(1);
    }
}

$dashboardView = (string)file_get_contents($root . '/app/view/dashboard/index.html');
$layoutView = (string)file_get_contents($root . '/app/view/layout/main.html');
$candidateController = (string)file_get_contents($root . '/app/controller/PlanningRegulatoryCandidate.php');
$candidateIndex = (string)file_get_contents($root . '/app/view/planning_regulatory_candidate/index.html');
$candidateView = (string)file_get_contents($root . '/app/view/planning_regulatory_candidate/view.html');
$changeEventIndex = (string)file_get_contents($root . '/app/view/planning_change_event/index.html');
$recordTemplateIndex = (string)file_get_contents($root . '/app/view/record_form_template/index.html');
$recordTemplateView = (string)file_get_contents($root . '/app/view/record_form_template/view.html');
$recordTemplateController = (string)file_get_contents($root . '/app/controller/RecordFormTemplate.php');
$recordInstanceIndex = (string)file_get_contents($root . '/app/view/record_form_instance/index.html');
$route = (string)file_get_contents($root . '/route/app.php');
$dashboardService = (string)file_get_contents($root . '/app/service/DashboardMetricService.php');
$recordInstanceController = (string)file_get_contents($root . '/app/controller/RecordFormInstance.php');

assert_contains('qmsChartOrEmpty', $dashboardView, 'Dashboard should render empty chart states when datasets are all zero');
assert_contains('暂无校准记录', $dashboardView, 'Dashboard should explain empty calibration data');
assert_contains('暂无 CAPA 数据', $dashboardView, 'Dashboard should explain empty CAPA trend data');
assert_contains('array_sum($values)', $dashboardService, 'Dashboard metric service/view should support zero-sum chart handling');
assert_not_contains('/record_form_instance/create?template_id=', $dashboardView, 'Dashboard new-record action must not link to an empty template_id create URL');
assert_contains('/record_form_template/index', $dashboardView, 'Dashboard new-record action should send users to choose a record template first');
assert_contains('请先选择记录模板', $recordInstanceController, 'Record instance create should redirect empty template_id requests to template selection');

assert_contains('外部变化管理', $layoutView, 'Planning navigation should group candidate pool and change events');
assert_contains('pendingRegulatoryCandidateCount', $layoutView . $candidateController, 'Navigation should expose pending regulatory candidate badge count');
assert_contains('{$pendingRegulatoryCandidateCount}', $layoutView, 'Regulatory candidate menu should render a numeric badge only when pending items exist');
assert_not_contains('法规候选池（待确认）', $layoutView, 'Fixed menu name should remain stable and not include dynamic status text');

assert_contains('sourceTrustLabels', $candidateController, 'Regulatory candidate controller should translate machine source trust words');
assert_contains('来源待核验', $candidateIndex . $candidateView, 'Regulatory candidate pages should display business-facing source trust labels');
assert_contains('建议动作', $candidateView, 'Regulatory candidate detail should separate suggested actions from preliminary conclusions');
assert_contains('初步结论', $candidateView, 'Regulatory candidate detail should keep preliminary conclusion as a separate concept');
assert_contains('已生效', $candidateView . $candidateController, 'Regulatory candidate detail should expose effective-date urgency');
foreach (['确认适用', '确认不适用', '暂缓', '转正式变更事件'] as $label) {
    assert_contains($label, $candidateView, 'Regulatory candidate detail should expose action button: ' . $label);
}
assert_contains('planning/regulatory-candidates/review', $route, 'Route should support regulatory candidate manual review actions');
assert_contains('planning/regulatory-candidates/promote', $route, 'Route should support promoting a candidate into a formal change event');
assert_contains('promote', $candidateController, 'Regulatory candidate controller should implement promotion');

assert_contains('去候选池看看', $changeEventIndex, 'Empty change-event page should guide users back to the candidate pool');
assert_contains('候选经人工确认后', $changeEventIndex, 'Empty change-event page should explain candidate-to-event flow');

assert_contains('duplicate_doc_number_count', $recordTemplateController . $recordTemplateIndex, 'Record template list should flag duplicate controlled numbers');
assert_contains('编号重复', $recordTemplateIndex, 'Record template list should warn about duplicate document numbers');
assert_contains('作废/换版', $recordTemplateIndex . $recordTemplateView, 'Published record templates should guide obsolete/version workflow instead of physical delete');
assert_contains("status === 'draft'", $recordTemplateController, 'RecordFormTemplate delete should only allow draft templates');
assert_not_contains("确认删除？')\">删除", $recordTemplateIndex, 'Published template rows should not show a plain delete button');

assert_contains('填写人', $recordInstanceIndex, 'Record instance list should show filler/operator column');
assert_contains('审核人', $recordInstanceIndex, 'Record instance list should show reviewer column');
assert_contains('已形成PDF', $recordInstanceIndex, 'Generated record status should be business-facing, not just technical');
assert_contains('2025 年度记录运行确认', $recordInstanceIndex, 'Annual review action should have an explicit business-facing label');

echo "qms_g4r7_trial_readiness_smoke passed\n";
