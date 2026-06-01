<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\AiChatService;
use app\service\AiSettingsService;
use app\service\PageContextBuilder;
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

$userA = qms_uuid();
$userB = qms_uuid();
$sessionId = qms_uuid();
$now = date('Y-m-d H:i:s');
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
assert_contains('AiSettingsService::isConfigured', $assistantService, 'AiAssistantService uses AiSettingsService');
assert_contains('DeepSeekService::chat', $assistantService, 'AiAssistantService uses DeepSeekService');

echo "qms_ai_chat_smoke passed\n";
