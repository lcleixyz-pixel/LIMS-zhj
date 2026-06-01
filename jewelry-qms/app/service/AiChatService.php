<?php
declare(strict_types=1);

namespace app\service;

use think\exception\HttpException;
use think\facade\Config;
use think\facade\Db;

class AiChatService
{
    public static function ensureSchema(): void
    {
        AiSettingsService::ensureSchema();
    }

    public static function createSession(string $companyId, string $userId, array $pageContext, string $contextMode): array
    {
        self::ensureSchema();
        if (!in_array($contextMode, ['general', 'context', 'expert'], true)) {
            $contextMode = 'context';
        }

        $id = qms_uuid();
        $now = date('Y-m-d H:i:s');
        $page = $pageContext['page'] ?? [];
        Db::name('ai_chat_sessions')->insert([
            'id' => $id,
            'company_id' => $companyId,
            'user_id' => $userId,
            'title' => (string)($page['title'] ?? '新对话'),
            'context_mode' => $contextMode,
            'agent_mode' => $contextMode === 'expert' ? 'expert' : 'assistant',
            'page_route' => (string)($page['route'] ?? ''),
            'page_record_id' => ($page['record_id'] ?? '') !== '' ? (string)$page['record_id'] : null,
            'last_message_at' => $now,
            'message_count' => 0,
            'created' => $now,
            'modified' => $now,
        ]);

        return self::sessionPayload($id);
    }

    public static function sendMessage(
        string $companyId,
        string $sessionId,
        string $userId,
        string $content,
        array $pageContext,
        string $contextMode
    ): array {
        self::ensureSchema();
        if (!AiSettingsService::isConfigured($companyId)) {
            throw new \RuntimeException('DeepSeek API 未配置');
        }

        $session = self::assertSessionOwned($companyId, $sessionId, $userId);
        $content = trim($content);
        if ($content === '') {
            throw new \InvalidArgumentException('消息不能为空');
        }
        if (!in_array($contextMode, ['general', 'context', 'expert'], true)) {
            $contextMode = (string)$session['context_mode'];
        }

        $snapshot = self::contextSnapshot($pageContext);
        $history = self::buildChatHistory($sessionId);
        $messages = self::buildPromptMessages($content, $pageContext, $contextMode, $history);
        $useJsonDraft = ($pageContext['form_schema'] ?? null) !== null;

        $now = date('Y-m-d H:i:s');
        $userMessageId = qms_uuid();
        Db::name('ai_chat_messages')->insert([
            'id' => $userMessageId,
            'session_id' => $sessionId,
            'role' => 'user',
            'content' => $content,
            'context_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            'created' => $now,
        ]);

        $result = DeepSeekService::chat($messages, [
            'company_id' => $companyId,
            'max_tokens' => (int)Config::get('qms.ai.chat_max_tokens', 2048),
            'timeout' => (int)Config::get('qms.ai.chat_timeout', 180),
            'response_format' => $useJsonDraft ? ['type' => 'json_object'] : null,
        ]);

        $parsed = self::parseAssistantResponse((string)$result['content'], $useJsonDraft);
        $draft = null;
        if (($parsed['draft'] ?? null) !== null) {
            $draft = self::sanitizeDraft($parsed['draft'], $pageContext['form_schema'] ?? []);
        }

        $assistantId = qms_uuid();
        Db::name('ai_chat_messages')->insert([
            'id' => $assistantId,
            'session_id' => $sessionId,
            'role' => 'assistant',
            'content' => (string)$parsed['reply'],
            'context_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            'draft_json' => $draft ? json_encode($draft, JSON_UNESCAPED_UNICODE) : null,
            'token_usage' => $result['usage'] ? json_encode($result['usage'], JSON_UNESCAPED_UNICODE) : null,
            'created' => $now,
        ]);

        Db::name('ai_chat_sessions')->where('id', $sessionId)->update([
            'context_mode' => $contextMode,
            'last_message_at' => $now,
            'message_count' => (int)$session['message_count'] + 2,
            'modified' => $now,
        ]);

        return [
            'message_id' => $assistantId,
            'content' => (string)$parsed['reply'],
            'draft' => $draft,
            'expert_placeholder' => ($contextMode === 'expert'),
        ];
    }

    public static function listSessions(string $companyId, string $userId, int $limit = 20): array
    {
        self::ensureSchema();

        return Db::name('ai_chat_sessions')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->order('last_message_at', 'desc')
            ->limit(max(1, $limit))
            ->select()
            ->toArray();
    }

    public static function getMessages(string $companyId, string $sessionId, string $userId): array
    {
        self::assertSessionOwned($companyId, $sessionId, $userId);

        $rows = Db::name('ai_chat_messages')
            ->where('session_id', $sessionId)
            ->order('created', 'asc')
            ->select()
            ->toArray();

        foreach ($rows as &$row) {
            $row['draft_json'] = json_decode((string)($row['draft_json'] ?? ''), true);
            $row['context_snapshot'] = json_decode((string)($row['context_snapshot'] ?? ''), true);
        }

        return $rows;
    }

    public static function purgeExpiredSessions(string $companyId): int
    {
        self::ensureSchema();
        $days = AiSettingsService::getRetentionDays($companyId);
        $threshold = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
        $sessionIds = Db::name('ai_chat_sessions')
            ->where('company_id', $companyId)
            ->where(function ($q) use ($threshold) {
                $q->where('last_message_at', '<', $threshold)->whereOr(function ($q2) use ($threshold) {
                    $q2->whereNull('last_message_at')->where('created', '<', $threshold);
                });
            })
            ->column('id');

        return self::deleteSessionsByIds($sessionIds);
    }

    public static function clearAllSessions(string $companyId, ?string $adminUserId = null): int
    {
        self::ensureSchema();
        $sessionIds = Db::name('ai_chat_sessions')->where('company_id', $companyId)->column('id');

        return self::deleteSessionsByIds($sessionIds);
    }

    public static function clearUserSessions(string $companyId, string $userId): int
    {
        self::ensureSchema();
        $sessionIds = Db::name('ai_chat_sessions')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->column('id');

        return self::deleteSessionsByIds($sessionIds);
    }

    public static function sanitizeDraft(array $draft, array $formSchema): array
    {
        $allowed = $formSchema['allowed_fields'] ?? [];
        $blocked = ['id', 'company_id', 'created', 'modified', 'created_by', 'modified_by', 'publish', 'soft_delete', '__token__'];
        $fields = [];
        foreach (($draft['fields'] ?? []) as $name => $value) {
            $name = (string)$name;
            if (in_array($name, $blocked, true)) {
                continue;
            }
            if ($allowed !== [] && !in_array($name, $allowed, true)) {
                continue;
            }
            $fields[$name] = $value;
        }

        return [
            'module' => (string)($draft['module'] ?? ($formSchema['module'] ?? '')),
            'fields' => $fields,
            'allowed_fields' => array_keys($fields),
            'warnings' => $draft['warnings'] ?? [],
        ];
    }

    private static function assertSessionOwned(string $companyId, string $sessionId, string $userId): array
    {
        $session = Db::name('ai_chat_sessions')
            ->where('id', $sessionId)
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->find();
        if (!$session) {
            throw new HttpException(404, '会话不存在或无权访问');
        }

        return $session;
    }

    private static function deleteSessionsByIds(array $sessionIds): int
    {
        if ($sessionIds === []) {
            return 0;
        }
        Db::name('ai_chat_messages')->whereIn('session_id', $sessionIds)->delete();
        Db::name('ai_chat_sessions')->whereIn('id', $sessionIds)->delete();

        return count($sessionIds);
    }

    private static function sessionPayload(string $sessionId): array
    {
        return (array)Db::name('ai_chat_sessions')->where('id', $sessionId)->find();
    }

    private static function contextSnapshot(array $pageContext): array
    {
        return [
            'page' => $pageContext['page'] ?? [],
            'record_summary' => $pageContext['record_summary'] ?? null,
            'compliance_hints' => $pageContext['compliance_hints'] ?? [],
            'form_schema_module' => ($pageContext['form_schema']['module'] ?? null),
        ];
    }

    private static function buildChatHistory(string $sessionId): array
    {
        $rows = Db::name('ai_chat_messages')
            ->where('session_id', $sessionId)
            ->whereIn('role', ['user', 'assistant'])
            ->order('created', 'desc')
            ->limit(10)
            ->select()
            ->toArray();

        $rows = array_reverse($rows);
        $messages = [];
        foreach ($rows as $row) {
            $messages[] = [
                'role' => (string)$row['role'],
                'content' => (string)$row['content'],
            ];
        }

        return array_slice($messages, -8);
    }

    private static function buildPromptMessages(string $content, array $pageContext, string $contextMode, array $history): array
    {
        $systemParts = [
            '你是珠宝检测实验室 QMS 的使用助手，熟悉 ISO/IEC 17025 与系统各模块。',
            '只给建议和草稿，不声称已保存数据；涉及正式记录时提醒用户核对后手动保存。',
            '有 Word 原件时引导用户使用 AI 文档助理导入。',
        ];

        if ($contextMode === 'expert') {
            $systemParts[] = '你是实验室迎审评审专家（预览版）。当前仅提供基于合规驾驶舱摘要的只读建议。';
        }

        if ($contextMode !== 'general') {
            $systemParts[] = '当前页面上下文：' . json_encode(self::contextSnapshot($pageContext), JSON_UNESCAPED_UNICODE);
        }

        if (($pageContext['form_schema'] ?? null) !== null) {
            $systemParts[] = '若用户请求填表草稿，返回 JSON：{"reply":"markdown说明","draft":{"module":"...","fields":{...},"warnings":[]}}，fields 键必须来自 allowed_fields。';
            $systemParts[] = 'allowed_fields：' . json_encode($pageContext['form_schema']['allowed_fields'] ?? [], JSON_UNESCAPED_UNICODE);
        }

        $messages = [['role' => 'system', 'content' => implode("\n", $systemParts)]];
        foreach ($history as $item) {
            $messages[] = $item;
        }
        $messages[] = ['role' => 'user', 'content' => $content];

        return $messages;
    }

    private static function parseAssistantResponse(string $content, bool $expectJson): array
    {
        if (!$expectJson) {
            return ['reply' => $content, 'draft' => null];
        }

        $json = json_decode($content, true);
        if (!is_array($json)) {
            return ['reply' => $content, 'draft' => null];
        }

        return [
            'reply' => (string)($json['reply'] ?? $content),
            'draft' => is_array($json['draft'] ?? null) ? $json['draft'] : null,
        ];
    }
}
