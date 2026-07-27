<?php
declare(strict_types=1);

/**
 * 8021 四角色中文友好整改的试运行模板换版。
 *
 * 默认只预览：
 *   php scripts/qms_four_role_ux_trial_backfill.php
 *
 * 明确写入 8021：
 *   QMS_FOUR_ROLE_UX_APPLY=1 php scripts/qms_four_role_ux_trial_backfill.php --apply
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\model\RecordFormTemplate;
use app\service\G2ExpansionBatch2BlueprintService;
use app\service\RecordFormSchemaService;
use app\service\RecordFormTemplateRevisionService;
use app\service\TrialModeService;
use think\facade\Config;
use think\facade\Db;

(new think\App())->initialize();

$apply = in_array('--apply', $argv ?? [], true);
$applyAllowed = in_array(strtolower((string)getenv('QMS_FOUR_ROLE_UX_APPLY')), ['1', 'true'], true);
$databaseName = (string)Config::get('database.connections.mysql.database', '');
$expectedBatch = 'GOV-TRIAL-20260724';
$targetNumber = 'SIM-XZTC/BG-02-01';

if (!TrialModeService::isEnabled()) {
    throw new DomainException('拒绝执行：当前环境未开启试运行模式。');
}
if (TrialModeService::trialBatch() !== $expectedBatch) {
    throw new DomainException('拒绝执行：当前试运行批次不是 ' . $expectedBatch . '。');
}
if ($databaseName !== 'jewelry_qms') {
    throw new DomainException('拒绝执行：数据库名称不是 8021 隔离栈预期的 jewelry_qms。');
}

$blueprint = null;
foreach (G2ExpansionBatch2BlueprintService::templates() as $candidate) {
    if (($candidate['doc_number'] ?? '') === 'XZTC/BG-02-01') {
        $blueprint = $candidate;
        break;
    }
}
if (!is_array($blueprint)) {
    throw new DomainException('未找到 XZTC/BG-02-01 字段蓝图。');
}
$desiredSchema = RecordFormSchemaService::encode($blueprint['field_schema']);

$currentRows = RecordFormTemplate::where('doc_number', $targetNumber)
    ->where('trial_batch', $expectedBatch)
    ->where('status', 'trial_ready')
    ->where('soft_delete', 0)
    ->order('created', 'desc')
    ->select();
if (count($currentRows) !== 1) {
    throw new DomainException('拒绝执行：预期恰好 1 个当前试运行版本，实际为 ' . count($currentRows) . ' 个。');
}
/** @var RecordFormTemplate $source */
$source = $currentRows[0];
$openDraft = RecordFormTemplateRevisionService::openDraftFor($source);
$currentSchemaHash = hash('sha256', (string)$source->field_schema);
$desiredSchemaHash = hash('sha256', $desiredSchema);
$upToDate = hash_equals($currentSchemaHash, $desiredSchemaHash);

$preview = [
    'mode' => $apply && $applyAllowed ? 'apply' : 'dry-run',
    'environment' => [
        'trial_mode' => true,
        'trial_batch' => TrialModeService::trialBatch(),
        'database' => $databaseName,
        'production_8010_touched' => false,
    ],
    'source' => [
        'id' => (string)$source->id,
        'doc_number' => (string)$source->doc_number,
        'version' => (string)$source->version,
        'status' => (string)$source->status,
        'schema_sha256' => $currentSchemaHash,
    ],
    'candidate' => [
        'next_version' => RecordFormTemplateRevisionService::nextVersion((string)$source->version),
        'schema_sha256' => $desiredSchemaHash,
        'up_to_date' => $upToDate,
        'open_draft_id' => $openDraft ? (string)$openDraft->id : null,
    ],
];

if ($apply && !$applyAllowed) {
    fwrite(STDERR, json_encode($preview, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
    fwrite(STDERR, "拒绝写入：请同时设置 QMS_FOUR_ROLE_UX_APPLY=1。\n");
    exit(2);
}
if (!$apply) {
    echo json_encode($preview, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}
if ($upToDate) {
    $preview['result'] = [
        'changed' => false,
        'message' => '当前试运行版本已是目标字段契约，无需再次换版。',
    ];
    echo json_encode($preview, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}
if ($openDraft) {
    throw new DomainException('拒绝覆盖：当前版本已有修订草稿 ' . (string)$openDraft->id . '，请先人工处理。');
}

$result = Db::transaction(static function () use ($source, $desiredSchema, $blueprint): array {
    $revision = RecordFormTemplateRevisionService::createDraftRevision(
        $source,
        '四角色中文友好整改：补齐月份、日期、数值单位、符合性选择、人员选择及异常处置条件必填。'
    );
    /** @var RecordFormTemplate $draft */
    $draft = $revision['template'];
    $draft->field_schema = $desiredSchema;
    $draft->print_template_key = (string)$blueprint['print_template_key'];
    $draft->review_status = 'completed';
    $draft->review_note = '系统字段契约测试通过；在 8021 进入四角色试运行复验。';
    $draft->reviewed_at = date('Y-m-d H:i:s');
    $draft->save();

    $readinessErrors = TrialModeService::readinessErrors($draft);
    if ($readinessErrors !== []) {
        throw new DomainException('新版本未达到试运行条件：' . implode('；', $readinessErrors));
    }

    $draft->save([
        'status' => 'trial_ready',
        'publish' => 1,
        'trial_batch' => TrialModeService::trialBatch(),
        'trial_approved_by' => (string)$source->trial_approved_by,
        'trial_approved_at' => date('Y-m-d H:i:s'),
        'trial_note' => '四角色中文友好整改自动换版；仅限 8021 模拟运行，不等同于正式发布。',
    ]);
    $source->save([
        'status' => 'obsolete',
        'publish' => 0,
    ]);

    return [
        'new_id' => (string)$draft->id,
        'new_version' => (string)$draft->version,
        'new_status' => (string)$draft->status,
        'old_id' => (string)$source->id,
        'old_status' => (string)$source->status,
        'schema_sha256' => hash('sha256', (string)$draft->field_schema),
    ];
});

$preview['result'] = $result;
echo json_encode($preview, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
