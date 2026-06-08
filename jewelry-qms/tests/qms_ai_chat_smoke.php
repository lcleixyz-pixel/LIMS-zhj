<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\AiChatService;
use app\service\AiContextToolService;
use app\service\AiSettingsService;
use app\service\CopilotReadService;
use app\service\PageContextBuilder;
use app\service\QmsDocumentStructureService;
use app\service\SettingsCipher;
use think\exception\HttpException;
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

function table_exists(string $name): bool
{
    return (int)Db::query(
        'SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        [$name]
    )[0]['total'] > 0;
}

$root = dirname(__DIR__);
$migration = (string)file_get_contents($root . '/database/migrations/20260601_ai_chat_assistant.sql');
$route = (string)file_get_contents($root . '/route/app.php');
$config = (string)file_get_contents($root . '/config/qms.php');
$rbac = (string)file_get_contents($root . '/app/middleware/Rbac.php');
$audit = (string)file_get_contents($root . '/app/middleware/AuditLog.php');
$console = (string)file_get_contents($root . '/config/console.php');
$layout = (string)file_get_contents($root . '/app/view/layout/main.html');
$copilotJs = (string)file_get_contents($root . '/public/static/js/qms-copilot.js');
$assistantService = (string)file_get_contents($root . '/app/service/AiAssistantService.php');

assert_contains('CREATE TABLE IF NOT EXISTS `system_settings`', $migration, 'Migration creates system_settings');
assert_contains('CREATE TABLE IF NOT EXISTS `ai_chat_sessions`', $migration, 'Migration creates ai_chat_sessions');
assert_contains('CREATE TABLE IF NOT EXISTS `ai_chat_messages`', $migration, 'Migration creates ai_chat_messages');

AiSettingsService::ensureSchema();
assert_true(table_exists('system_settings'), 'system_settings table exists after ensureSchema');
assert_true(table_exists('ai_chat_sessions'), 'ai_chat_sessions table exists after ensureSchema');
assert_true(table_exists('ai_chat_messages'), 'ai_chat_messages table exists after ensureSchema');

if (SettingsCipher::canEncrypt()) {
    $plain = 'sk-test-' . bin2hex(random_bytes(4));
    $encrypted = SettingsCipher::encrypt($plain);
    assert_contains('v1:', $encrypted, 'Encrypted secret uses v1 prefix');
    assert_true(SettingsCipher::decrypt($encrypted) === $plain, 'SettingsCipher round trip works');
    $parts = explode(':', $encrypted, 4);
    $parts[2] = base64_encode(str_repeat('x', 16));
    assert_true(SettingsCipher::decrypt(implode(':', $parts)) === null, 'Tampered tag fails decrypt');
}

$companyId = (string)Config::get('qms.company_id');
$now = date('Y-m-d H:i:s');
assert_true(AiSettingsService::get($companyId, 'ai.deepseek.api_key') === null, 'get() returns null for secret keys');

$configResult = AiSettingsService::resolveAiConfig($companyId);
assert_true(isset($configResult['source']), 'resolveAiConfig exposes source');
assert_true(isset($configResult['api_key']), 'resolveAiConfig exposes api_key key');

$testResult = AiSettingsService::testConnection($companyId);
assert_true(isset($testResult['source']), 'testConnection returns source field');

$draft = AiChatService::sanitizeDraft([
    'module' => 'training',
    'fields' => [
        'title' => '测试',
        'id' => 'blocked',
        '__token__' => 'blocked',
    ],
    'warnings' => [],
], PageContextBuilder::formSchemaFor('training', 'add') ?? ['allowed_fields' => ['title']]);
assert_true(!isset($draft['fields']['id']), 'sanitizeDraft drops id');
assert_true(!isset($draft['fields']['__token__']), 'sanitizeDraft drops __token__');
assert_true(isset($draft['fields']['title']), 'sanitizeDraft keeps allowed fields');

QmsDocumentStructureService::seedAll();

$recordReviewContext = PageContextBuilder::fromPageMeta(
    $companyId,
    'recordformtemplate',
    'review',
    null,
    'context',
    '记录模板复核',
    'record_form_template/review'
);
assert_true(($recordReviewContext['record_summary']['module'] ?? '') === 'record_form_template', 'Record review page exposes record-form summary to Copilot');
assert_true(isset($recordReviewContext['record_summary']['schema_gap_blocks']), 'Record review context includes schema gap counts');
assert_contains('对照程序记录要求缺哪些字段', json_encode($recordReviewContext, JSON_UNESCAPED_UNICODE) ?: '', 'Record review context suggests field-gap Copilot prompt');

$structuredId = (string)Db::name('qms_structured_documents')
    ->where('doc_number', 'XZTC/CX-26-2022')
    ->where('soft_delete', 0)
    ->value('id');
assert_true($structuredId !== '', 'AI smoke has a structured procedure for planning context');
$planningContext = PageContextBuilder::fromPageMeta(
    $companyId,
    'planningstructure',
    'changeimpact',
    $structuredId,
    'context',
    '变更影响预检',
    'planning/structures/change-impact'
);
assert_true(($planningContext['record_summary']['module'] ?? '') === 'planning_structure', 'Planning structure page exposes planning summary to Copilot');
assert_true(($planningContext['record_summary']['selected_document']['doc_number'] ?? '') === 'XZTC/CX-26-2022', 'Planning context keeps selected structured document');
assert_contains('XZTC/BG-', json_encode($planningContext['record_summary']['selected_change_impact']['record_forms'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 'Planning context includes affected BG record forms');
assert_contains('改这份程序会影响哪些 BG', json_encode($planningContext, JSON_UNESCAPED_UNICODE) ?: '', 'Planning context suggests change-impact Copilot prompt');

$annualPlanTemplate = Db::name('record_form_templates')
    ->where('doc_number', 'XZTC/BG-01-01')
    ->where('soft_delete', 0)
    ->where('status', 'published')
    ->order('created', 'asc')
    ->find();
$trainingRecordTemplate = Db::name('record_form_templates')
    ->where('doc_number', 'XZTC/BG-01-02')
    ->where('soft_delete', 0)
    ->where('status', 'published')
    ->order('created', 'asc')
    ->find();
assert_true(is_array($annualPlanTemplate), 'AI smoke has annual training plan template');
assert_true(is_array($trainingRecordTemplate), 'AI smoke has training record template');

$annualPlanInstanceId = qms_uuid();
$trainingRecordInstanceId = qms_uuid();
try {
    Db::name('record_form_instances')->insert([
        'id' => $annualPlanInstanceId,
        'company_id' => $companyId,
        'template_id' => (string)$annualPlanTemplate['id'],
        'template_name' => (string)$annualPlanTemplate['name'],
        'template_module' => (string)$annualPlanTemplate['module'],
        'template_version' => (string)$annualPlanTemplate['version'],
        'template_print_template_key' => (string)$annualPlanTemplate['print_template_key'],
        'template_field_schema' => (string)$annualPlanTemplate['field_schema'],
        'doc_number' => 'XZTC/BG-01-01',
        'record_title' => '2097运行记录-XZTC/BG-01-01-年度人员培训计划表',
        'field_values' => json_encode([
            'plan_year' => '2097',
            'training_plan_items' => [
                [
                    'training_time' => '2097-03',
                    'training_content' => '珠宝玉石检测标准与记录控制要求',
                    'training_target' => '检测人员、授权签字人',
                    'training_department' => '检测室',
                    'remarks' => 'AI context smoke',
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        'status' => 'draft',
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('record_form_instances')->insert([
        'id' => $trainingRecordInstanceId,
        'company_id' => $companyId,
        'template_id' => (string)$trainingRecordTemplate['id'],
        'template_name' => (string)$trainingRecordTemplate['name'],
        'template_module' => (string)$trainingRecordTemplate['module'],
        'template_version' => (string)$trainingRecordTemplate['version'],
        'template_print_template_key' => (string)$trainingRecordTemplate['print_template_key'],
        'template_field_schema' => (string)$trainingRecordTemplate['field_schema'],
        'doc_number' => 'XZTC/BG-01-02',
        'record_title' => '2097运行记录-XZTC/BG-01-02-人员培训记录表',
        'field_values' => json_encode([
            'training_date' => '2097-03-15',
            'training_topic' => '珠宝玉石检测标准与记录控制要求',
            'trainer' => '张晓磊',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        'status' => 'draft',
        'created' => $now,
        'modified' => $now,
    ]);

    $recordInstanceContext = PageContextBuilder::fromPageMeta(
        $companyId,
        'recordforminstance',
        'view',
        $annualPlanInstanceId,
        'context',
        '年度人员培训计划表',
        'record_form_instance/view'
    );
    $recordInstanceContextJson = json_encode($recordInstanceContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    assert_true(($recordInstanceContext['record_summary']['module'] ?? '') === 'record_form_instance', 'Record instance page exposes record instance summary to Copilot');
    assert_true(($recordInstanceContext['record_summary']['current_instance']['doc_number'] ?? '') === 'XZTC/BG-01-01', 'Record instance context keeps current instance doc number');
    assert_contains('珠宝玉石检测标准与记录控制要求', $recordInstanceContextJson, 'Record instance context includes current training plan items');
    assert_contains('XZTC/BG-01-02', $recordInstanceContextJson, 'Record instance context includes related training record template');
    assert_contains('2097运行记录-XZTC/BG-01-02-人员培训记录表', $recordInstanceContextJson, 'Record instance context includes existing year training record drafts');
    assert_contains('根据当前年度培训计划生成培训记录草稿', $recordInstanceContextJson, 'Record instance context suggests training-record Copilot prompt');

    assert_true(class_exists(CopilotReadService::class), 'CopilotReadService exists for full read-only QMS access');
    assert_true(class_exists(AiContextToolService::class), 'AiContextToolService exists for controlled read-pack assembly');
    $readPack = AiContextToolService::buildReadPack(
        $companyId,
        '根据当前 2097 年度培训计划生成培训记录草稿',
        $recordInstanceContext
    );
    $readPackJson = json_encode($readPack, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    assert_true(($readPack['readonly'] ?? false) === true, 'Copilot read pack is explicitly readonly');
    assert_contains('record_form_instance.current', $readPackJson, 'Read pack includes current record instance source');
    assert_contains('record_form_template.related', $readPackJson, 'Read pack includes related record template source');
    assert_contains('record_form_instance.related_year', $readPackJson, 'Read pack includes related yearly record instances');
    assert_contains('employee.list', $readPackJson, 'Read pack includes employee list source');
    assert_contains('equipment.list', $readPackJson, 'Read pack includes equipment list source');
    assert_contains('只读证据包', $readPackJson, 'Read pack explains readonly source boundaries');
    assert_contains('珠宝玉石检测标准与记录控制要求', $readPackJson, 'Read pack preserves annual training plan content');
    assert_contains('人员培训记录表', $readPackJson, 'Read pack includes target training record template');
} finally {
    Db::name('record_form_instances')->whereIn('id', [$annualPlanInstanceId, $trainingRecordInstanceId])->delete();
}

$userA = qms_uuid();
$userB = qms_uuid();
$sessionId = qms_uuid();
Db::name('ai_chat_sessions')->insert([
    'id' => $sessionId,
    'company_id' => $companyId,
    'user_id' => $userA,
    'title' => 'smoke',
    'context_mode' => 'context',
    'agent_mode' => 'assistant',
    'last_message_at' => $now,
    'message_count' => 0,
    'created' => $now,
    'modified' => $now,
]);

$crossUserFailed = false;
try {
    AiChatService::getMessages($companyId, $sessionId, $userB);
} catch (HttpException) {
    $crossUserFailed = true;
}
assert_true($crossUserFailed, 'assertSessionOwned blocks cross-user access');

$oldSessionId = qms_uuid();
$oldTime = date('Y-m-d H:i:s', strtotime('-120 days'));
Db::name('ai_chat_sessions')->insert([
    'id' => $oldSessionId,
    'company_id' => $companyId,
    'user_id' => $userA,
    'title' => 'old',
    'context_mode' => 'context',
    'agent_mode' => 'assistant',
    'last_message_at' => $oldTime,
    'message_count' => 0,
    'created' => $oldTime,
    'modified' => $oldTime,
]);
$purged = AiChatService::purgeExpiredSessions($companyId);
assert_true($purged >= 1, 'purgeExpiredSessions deletes expired sessions');

assert_contains("Route::post('ai_chat/send'", $route, 'Route registers ai_chat send');
assert_contains("Route::get('ai_settings/index'", $route, 'Route registers ai_settings index');
assert_contains('PageContext::class', $route, 'Route group loads PageContext middleware');
assert_contains("'ai_chat'", $config, 'quality_manager permissions include ai_chat');
assert_contains("'save'", $rbac, 'RBAC treats save as write action');
assert_contains("'purge'", $audit, 'AuditLog records purge actions');
assert_contains('AiPurgeChat::class', $console, 'Console registers ai:purge-chat command');
assert_contains('data-qms-controller', $layout, 'Layout exposes page context data attributes');
assert_contains('qmsCopilotFab', $layout, 'Layout renders copilot FAB conditionally');
assert_contains('qmsCopilotEnabled', $layout, 'Layout conditionally renders copilot for ai_chat permission');
assert_contains('X-CSRF-TOKEN', $copilotJs, 'Copilot fetch sends CSRF header');
assert_contains('page_meta[', $copilotJs, 'Copilot sends page_meta fields');
assert_contains('AiContextToolService::buildReadPack', (string)file_get_contents($root . '/app/service/AiChatService.php'), 'AiChatService attaches read-only evidence pack before model call');
assert_contains('AiSettingsService::isConfigured', $assistantService, 'AiAssistantService uses AiSettingsService');
assert_contains('DeepSeekService::chat', $assistantService, 'AiAssistantService uses DeepSeekService');

echo "qms_ai_chat_smoke passed\n";
