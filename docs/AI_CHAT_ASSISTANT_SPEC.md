# AI 聊天助手（QMS Copilot）开发规格 v3

> v3 基于 Codex 第二轮审查修订。主要变更：send 时页面上下文服务端重建、
> AES-256-GCM 密文格式规范、env 覆盖 UX、get/getSecret 分离。
>
> v2 变更摘要：API Key 加密存储与 env 优先、CSRF fetch 协议、会话三重隔离、
> draft 白名单、页面 meta 协议、History 审计、AiSettingsService company scope。

> 在现有 **AI 文档助理**（`/ai_assistant/index`，Word → 结构化入库）基础上，新增全局聊天抽屉，
> 提供使用指导、表单草稿填充、合规缺口解读；预留评审专家智能体模式。
>
> 已确认决策：
> - UI：全局浮动按钮 + 右侧侧栏抽屉
> - Phase 1 含表单草稿填充（只填充 DOM，不自动保存）
> - 允许读取当前页真实业务数据，支持三种上下文模式切换
> - API Key 可经管理页写入 `system_settings`（**应用级加密**）；调用一律走后端
> - 聊天记录保留 90 天；管理员可手动清空会话

## 概述

### 与现有 AI 模块的关系

| 模块 | 路由 | 职责 | 写库 |
|------|------|------|------|
| AI 文档助理 | `/ai_assistant/index` | 解析 Word → 结构化 JSON → 用户确认入库 | 确认后写入 |
| **AI 聊天助手** | 全局抽屉 + `/ai_chat/*` API | 问答、填表草稿、缺口解读 | **默认不写库** |
| AI 配置 | `/ai_settings/index` | 管理 DeepSeek Key、连接测试 | 加密写入 system_settings |

导航：
- 系统设置 → **AI 服务配置**（仅 admin）
- 系统设置 → AI 文档助理（已有）
- 任意页面右下角 **💬** 浮动按钮 → 聊天抽屉

### 核心设计原则

1. **AI 只建议，人确认** — 草稿仅填充表单字段，保存仍由用户点击各模块「保存」
2. **Key 不出前端** — 页面配置 POST 到服务端；前端只显示掩码；禁止 localStorage 存 Key
3. **上下文可切换** — 通用 / 上下文 / 评审专家（占位）三种模式，会话级记录
4. **最小必要数据** — 上下文模式只注入当前页摘要，不传整库
5. **可审计** — 消息留痕；90 天自动清理；admin 可清空；敏感操作写入 `histories`

---

## 一、数据库设计

### 1.1 system_settings — 系统配置（新建）

```sql
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `value_type` enum('string','json','secret') NOT NULL DEFAULT 'string',
  `description` varchar(255) DEFAULT NULL,
  `modified_by` varchar(36) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_setting_key` (`company_id`, `setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统级配置';
```

AI 相关 Key（`setting_key`）：

| Key | value_type | 说明 |
|-----|------------|------|
| `ai.deepseek.api_key` | secret | DeepSeek API Key（**加密后**存 DB；见 §1.5） |
| `ai.deepseek.model` | string | 默认 `deepseek-chat` |
| `ai.deepseek.base_url` | string | 默认 `https://api.deepseek.com` |
| `ai.chat.retention_days` | string | 默认 `90` |

### 1.2 ai_chat_sessions — 聊天会话

```sql
CREATE TABLE IF NOT EXISTS `ai_chat_sessions` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `title` varchar(200) DEFAULT '新对话',
  `context_mode` enum('general','context','expert') NOT NULL DEFAULT 'context',
  `agent_mode` enum('assistant','expert') NOT NULL DEFAULT 'assistant' COMMENT 'Phase3 评审专家',
  `page_route` varchar(200) DEFAULT NULL COMMENT '创建时会话所在 controller/action',
  `page_record_id` varchar(36) DEFAULT NULL,
  `last_message_at` datetime DEFAULT NULL,
  `message_count` int NOT NULL DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_time` (`user_id`, `last_message_at` DESC),
  KEY `idx_company_created` (`company_id`, `created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI 聊天会话';
```

### 1.3 ai_chat_messages — 聊天消息

```sql
CREATE TABLE IF NOT EXISTS `ai_chat_messages` (
  `id` varchar(36) NOT NULL,
  `session_id` varchar(36) NOT NULL,
  `role` enum('user','assistant','system') NOT NULL,
  `content` text NOT NULL,
  `context_snapshot` json DEFAULT NULL COMMENT '发送时注入的上下文摘要',
  `draft_json` json DEFAULT NULL COMMENT 'assistant 消息附带的表单草稿（已白名单过滤）',
  `token_usage` json DEFAULT NULL COMMENT '{"prompt":N,"completion":N}',
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_session_created` (`session_id`, `created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI 聊天消息';
```

### 1.4 Migration

`database/migrations/20260601_ai_chat_assistant.sql` — 三表 + 索引，幂等。

**禁止**在 migration 种子、SQL 导出样例、错误响应中写入真实 API Key。

### 1.5 API Key 存储策略

**读取优先级**（高 → 低）：

1. `env('DEEPSEEK_API_KEY')` — **生产环境推荐**
2. `system_settings` 中 `ai.deepseek.api_key`（经 `getSecret()` 解密）
3. `config/qms.php` 默认值（空）

**env 覆盖 UX（P2 修订）**：

- 配置页 `/ai_settings/index` 必须展示当前生效来源：`env` / `database` / `none`
- 当 env 非空时，页面顶部显示警告：**「环境变量 DEEPSEEK_API_KEY 正在覆盖数据库配置；此处保存的 Key 不会用于实际调用。」**
- DB Key 输入框仍可保存（备灾/切换 env 后生效），但 [测试连接] 与 LLM 调用均使用 env
- `resolveAiConfig()` 返回结构含 `source` 字段：`env|database|none`
- `testConnection()` 返回：

```json
{
  "ok": true,
  "message": "连接成功",
  "source": "env",
  "effective_masked_key": "sk-****abcd"
}
```

- Smoke test 断言：env 存在时 `resolveAiConfig()['source'] === 'env'`，且 DB 保存不改变 effective key

**写入规则**：

- 管理页保存 Key 时调用 `AiSettingsService::setSecret()`；**仅**当加密能力可用时才允许写入 DB
- `value_type=secret` 表示「加密存储 + 响应掩码」，**不是**明文入库
- **禁止**：写入应用日志、History details、异常 message、smoke test 输出、migration 种子
- 配置页**只能**调用 `getMaskedApiKey()`；禁止 View assign 明文 Key

**连接测试失败**时，错误信息统一为「连接失败，请检查 Key 与网络」，不 echo 上游响应体。

### 1.6 Secret 加密格式（P1 修订：可落地规范）

实现类：`app/service/SettingsCipher.php`（或 `AiSettingsService` 私有方法，但算法必须如下）

| 项 | 规定 |
|----|------|
| 算法 | `aes-256-gcm`（`openssl_encrypt` / `openssl_decrypt`，`OPENSSL_RAW_DATA`） |
| 密钥材料 | `hash('sha256', env('QMS_SETTINGS_CIPHER_KEY') ?: env('APP_KEY'), true)` → 32 字节 |
| IV | 每次 `setSecret` 随机生成 12 字节（禁止固定 IV） |
| 认证 | GCM 自带 auth tag；额外不做单独 HMAC |
| 密文存储格式 | 单行字符串：`v1:{base64(iv)}:{base64(tag)}:{base64(ciphertext)}` |
| 密钥缺失 | 若 `QMS_SETTINGS_CIPHER_KEY` 与 `APP_KEY` 均为空：**拒绝** `setSecret()`，返回「请配置 APP_KEY 或使用环境变量 DEEPSEEK_API_KEY」；允许 `resolveAiConfig()` 仅走 env |
| 密钥长度 | 派生后必须 32 字节；否则抛 `RuntimeException`，禁止降级 |
| 解密失败 | 视为损坏配置，不抛明文；`getSecret()` 返回 null，`resolveAiConfig` 回退 env |

**示例**（伪代码）：

```php
public static function encrypt(string $plain): string
{
    $key = self::deriveKey(); // 32 bytes
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return 'v1:' . base64_encode($iv) . ':' . base64_encode($tag) . ':' . base64_encode($cipher);
}
```

Smoke test：往返加解密；DB 中 `setting_value` 以 `v1:` 开头且不等于明文；错误 tag 解密失败。

---

## 二、上下文模式定义

| 模式 | `context_mode` | 注入数据 | 权限 |
|------|----------------|----------|------|
| 通用 | `general` | 模块帮助文案、字段说明、公开配置 | admin, quality_manager |
| 上下文 | `context` | 当前页 record 摘要、关联计数、Top5 合规缺口 | admin, quality_manager |
| 评审专家 | `expert` | **Phase 1 占位**：固定提示 + 简要缺口；Phase 3 全量工具调用 | admin, quality_manager |

### 2.1 页面上下文来源协议（P2 修订）

全局抽屉**不得**仅靠 URL 猜测 controller/action/id。在 `layout/main.html` 的 `<body>` 注入标准 meta：

```html
<body
  data-qms-controller="{$qmsPageContext.controller|default=''}"
  data-qms-action="{$qmsPageContext.action|default=''}"
  data-qms-record-id="{$qmsPageContext.record_id|default=''}"
  data-qms-route="{$qmsPageContext.route|default=''}"
  data-qms-module="{$qmsPageContext.module|default=''}"
  data-qms-title="{$qmsPageContext.title|default=''}"
>
```

**后端约定**：

- 新增 `app/middleware/PageContext.php` 或在 `BaseController` 初始化时 assign `$qmsPageContext`
- 字段来源：`$request->controller()`、`$request->action()`、`$request->param('id')`
- `module` = 小写 controller 名；`title` 由各页可选覆盖（如「员工详情 · 曹红」）
- `qms-copilot.js` 读取 `data-qms-*`，随 `/ai_chat/create`、`/ai_chat/send` 提交（见 §3.2、§5.2）

### 2.1.1 send/create 请求体（页面 meta 载荷）

前端 POST 时附带：

```json
{
  "session_id": "uuid",
  "content": "用户消息",
  "context_mode": "context",
  "page_meta": {
    "controller": "employee",
    "action": "view",
    "record_id": "uuid",
    "route": "employee/view",
    "module": "employee",
    "title": "员工详情 · 曹红"
  }
}
```

**服务端不得信任前端构造的业务摘要**；Controller 收到 `page_meta` 后必须调用：

```php
$pageContext = PageContextBuilder::fromPageMeta(
    $companyId,
    (string)$pageMeta['controller'],
    (string)$pageMeta['action'],
    $pageMeta['record_id'] ?? null,
    (string)($pageMeta['context_mode'] ?? 'context')
);
```

`fromPageMeta` 在服务端重建 `record_summary`、`form_schema`、`compliance_hints`；该结果写入 `ai_chat_messages.context_snapshot`。

### 2.2 PageContextBuilder 输出结构

```json
{
  "page": {
    "controller": "employee",
    "action": "view",
    "route": "employee/view",
    "record_id": "uuid",
    "title": "员工详情 · 曹红"
  },
  "record_summary": {
    "employee_number": "XZTC-RY-003",
    "name": "曹红",
    "appointments": 3,
    "training_records": 0,
    "competency_records": 0
  },
  "form_schema": null,
  "compliance_hints": [
    {"dimension": "personnel", "message": "人员维度 0 分：缺培训记录"}
  ]
}
```

**白名单**：`PageContextBuilder` 只允许查询预定义 controller → handler 映射表；禁止 AI 指定任意 SQL；所有查询强制 `company_id`。

### 2.3 表单页 form_schema（add/edit）

```json
{
  "module": "training",
  "allowed_fields": ["title", "training_date", "trainer", "training_type", "duration_hours", "content"],
  "fields": [
    {"name": "title", "label": "培训主题", "type": "text", "required": true},
    {"name": "training_date", "label": "培训日期", "type": "date"}
  ]
}
```

- `allowed_fields` 为服务端权威白名单；AI draft 入库前必须过滤
- 系统字段永久排除：`id`, `company_id`, `created`, `modified`, `created_by`, `modified_by`, `publish`, `soft_delete`, `__token__`
- Phase 1 覆盖：training, competency_record, employee_certificate, reference_material

---

## 三、服务层

### 3.1 AiSettingsService（显式 company scope + secret 分离）

路径：`app/service/AiSettingsService.php`

```php
// 普通配置：secret 类型返回 null，禁止解密明文泄露
public static function get(string $companyId, string $key, ?string $default = null): ?string;

// 仅 resolveAiConfig / SettingsCipher 内部链路调用；禁止 Controller/View 使用
private static function getSecret(string $companyId, string $key): ?string;

public static function set(string $companyId, string $key, string $value, string $type = 'string', ?string $userId = null): void;
public static function setSecret(string $companyId, string $key, string $plainValue, ?string $userId = null): void;
public static function getMaskedApiKey(string $companyId): string;
public static function resolveAiConfig(string $companyId): array; // 含 source, api_key(内存), model, base_url
public static function testConnection(string $companyId): array;  // 含 source, effective_masked_key
public static function isConfigured(string $companyId): bool;
public static function getConfigSource(string $companyId): string; // env|database|none
```

- `get()`：读取 `string`/`json`；若 `value_type=secret` **一律返回 null**（防误用）
- `getSecret()`：**private**，仅供 `resolveAiConfig()` 在 env 为空时解密 DB Key
- `getMaskedApiKey()`：对 env 或 DB 解密后的 Key 做掩码；永不返回完整 Key
- Controller 层统一传入 `(string)Config::get('qms.company_id')`

### 3.2 AiChatService（会话三重隔离 + 发送时上下文）

路径：`app/service/AiChatService.php`

```php
public static function createSession(
    string $companyId,
    string $userId,
    array $pageContext,
    string $contextMode
): array;

public static function sendMessage(
    string $companyId,
    string $sessionId,
    string $userId,
    string $content,
    array $pageContext,
    string $contextMode
): array;

public static function listSessions(string $companyId, string $userId, int $limit = 20): array;
public static function getMessages(string $companyId, string $sessionId, string $userId): array;
public static function purgeExpiredSessions(string $companyId): int;
public static function clearAllSessions(string $companyId, ?string $adminUserId = null): int;
public static function clearUserSessions(string $companyId, string $userId): int;
```

**发送时上下文（P1 修订）**：

1. `AiChat::send` Controller 从 POST 读取 `page_meta`、`context_mode`
2. 调用 `PageContextBuilder::fromPageMeta(...)` 得到**当前页** `$pageContext`（非会话创建时快照）
3. 传入 `sendMessage(..., $pageContext, $contextMode)`
4. Service 内：
   - `assertSessionOwned`
   - 按 `context_mode` 组装 prompt 上下文（general 时剥离 record_summary）
   - 调用 DeepSeek
   - 对 draft 调用 `sanitizeDraft($draft, $pageContext['form_schema'] ?? [])`
   - 用户消息与 assistant 消息的 `context_snapshot` 均写入**本次** `$pageContext` 摘要
5. 可选：更新 `ai_chat_sessions.context_mode = $contextMode`（允许同会话切换模式）

**切换页面后继续同会话**：每次 send 用**最新** `page_meta` 重建上下文，不依赖 `ai_chat_sessions.page_route` 旧值。

`createSession` 仍记录初始 `page_route/page_record_id` 供列表展示，但不作为 send 的上下文来源。

**会话归属校验（必须）**：

```php
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
```

- `sendMessage`、`getMessages`、`listSessions`（按 session 查消息时）**必须先**调用 `assertSessionOwned`
- `listSessions` 查询条件：`company_id + user_id`
- `purge/clearAll` 仅 admin；`clearUserSessions` 限 `company_id + user_id`
- 禁止仅按 `session_id` 单独查询

**Draft 白名单过滤（P1 修订）**：

```php
public static function sanitizeDraft(array $draft, array $formSchema): array
{
    $allowed = $formSchema['allowed_fields'] ?? [];
    $blocked = ['id','company_id','created','modified','created_by','modified_by','publish','soft_delete','__token__'];
    $fields = [];
    foreach (($draft['fields'] ?? []) as $name => $value) {
        if (in_array($name, $blocked, true)) continue;
        if ($allowed !== [] && !in_array($name, $allowed, true)) continue;
        $fields[$name] = $value;
    }
    return ['module' => $draft['module'] ?? '', 'fields' => $fields, 'allowed_fields' => array_keys($fields), 'warnings' => $draft['warnings'] ?? []];
}
```

`sendMessage` 返回的 `draft` 必须是 `sanitizeDraft` 之后的结果。

### 3.3 PageContextBuilder

路径：`app/service/PageContextBuilder.php`

```php
public static function fromPageMeta(
    string $companyId,
    string $controller,
    string $action,
    ?string $recordId,
    string $contextMode = 'context'
): array;

public static function fromRequestPayload(string $companyId, array $pageMeta, string $contextMode): array;

public static function complianceHints(string $companyId, int $limit = 5): array;
public static function formSchemaFor(string $controller, string $action): ?array;
```

- `fromRequestPayload`：校验 `page_meta` 字段后委托 `fromPageMeta`；非法 controller 抛 400
- `contextMode === 'general'` 时：`record_summary = null`，`compliance_hints = []`，仅保留 `form_schema`（若在 add/edit 页）
- `contextMode === 'expert'` 时：附加 `compliance_hints` 全量摘要（Phase 1 占位文案）

### 3.4 修改 AiAssistantService

将 Key 读取改为 `AiSettingsService::resolveAiConfig($companyId)`。

---

## 四、Prompt 设计

（与 v1 相同，略）

表单草稿 JSON 中 `fields` 的 key 必须来自当前页 `form_schema.allowed_fields`。

---

## 五、控制器与路由

### 5.1 AiSettings（admin 专用）

| 方法 | 路由 | 说明 |
|------|------|------|
| index | GET `/ai_settings/index` | 配置页 |
| save | POST `/ai_settings/save` | 保存 Key/模型（`setSecret`） |
| test | POST `/ai_settings/test` | 连接测试 |

### 5.2 AiChat API

| 方法 | 路由 | POST 参数 | 说明 |
|------|------|-----------|------|
| sessions | GET `/ai_chat/sessions` | — | 当前用户会话列表 |
| create | POST `/ai_chat/create` | `context_mode`, `page_meta` | 新建会话；Controller 调 `fromRequestPayload` |
| messages | GET `/ai_chat/messages` | `session_id` | 获取会话消息 |
| send | POST `/ai_chat/send` | `session_id`, `content`, `context_mode`, `page_meta` | 发送消息；**每次**重建 pageContext |
| purge | POST `/ai_chat/purge` | `scope` | admin 清空全部 / 清空当前用户 |

所有 Controller 方法从 Session 取 `user.id`，从 Config 取 `company_id`，传入 Service。

**AiChat::send 控制器伪代码**：

```php
public function send()
{
    $companyId = (string)Config::get('qms.company_id');
    $userId = (string)Session::get('user.id');
    $pageMeta = $this->request->post('page_meta/a', []);
    $contextMode = (string)$this->request->post('context_mode', 'context');
    $pageContext = PageContextBuilder::fromRequestPayload($companyId, $pageMeta, $contextMode);

    return json(AiChatService::sendMessage(
        $companyId,
        (string)$this->request->post('session_id'),
        $userId,
        (string)$this->request->post('content'),
        $pageContext,
        $contextMode
    ));
}
```

### 5.3 CSRF 协议（P1 修订）

认证路由组已启用 `FormTokenCheck`（`route/app.php`）。现有 `csrf.js` 仅覆盖表单与 jQuery AJAX，**不覆盖原生 fetch**。

`qms-copilot.js` 必须：

```javascript
function qmsCsrfToken() {
  var meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? (meta.getAttribute('content') || '') : '';
}

function qmsCopilotFetch(url, options) {
  options = options || {};
  options.headers = options.headers || {};
  options.headers['X-CSRF-TOKEN'] = qmsCsrfToken();
  options.headers['X-Requested-With'] = 'XMLHttpRequest';
  if ((options.method || 'GET').toUpperCase() === 'POST') {
    var body = options.body instanceof FormData ? options.body : new FormData();
    if (!(options.body instanceof FormData)) {
      body.append('payload', options.body || '');
      options.body = body;
    }
    if (!body.has('__token__')) {
      body.append('__token__', qmsCsrfToken());
    }
  }
  return fetch(url, options);
}
```

- 所有 `/ai_chat/*` POST 经 `qmsCopilotFetch` 发送
- Smoke test 断言 `qms-copilot.js` 含 `X-CSRF-TOKEN`
- 可选：扩展 `csrf.js` 提供 `window.qmsCsrfFetch` 供全站复用（Phase 1 至少 copilot 实现）

### 5.4 路由注册

```php
Route::get('ai_settings/index', 'AiSettings/index');
Route::post('ai_settings/save', 'AiSettings/save');
Route::post('ai_settings/test', 'AiSettings/test');

Route::get('ai_chat/sessions', 'AiChat/sessions');
Route::post('ai_chat/create', 'AiChat/create');
Route::get('ai_chat/messages', 'AiChat/messages');
Route::post('ai_chat/send', 'AiChat/send');
Route::post('ai_chat/purge', 'AiChat/purge');
```

RBAC：`save`, `test`, `send`, `create`, `purge` 加入 `$writeActions`。

---

## 六、前端 UI

### 6.1 全局布局注入（layout/main.html）

1. `<body data-qms-*>` 页面上下文（§2.1）
2. 浮动按钮 `#qmsCopilotFab`
3. 侧栏抽屉 `#qmsCopilotDrawer`
4. `/static/js/qms-copilot.js`、`/static/css/qms-copilot.css`
5. 在 `csrf.js` 之后加载 `qms-copilot.js`

### 6.2 applyDraft（P1 修订：前端白名单）

服务端返回的 `draft` 已含 `allowed_fields`。前端**仅**填充白名单内字段：

```javascript
window.qmsApplyFormDraft = function (draft) {
  var allowed = draft.allowed_fields || Object.keys(draft.fields || {});
  var blocked = ['__token__', 'id', 'company_id', 'created_by', 'modified_by'];
  allowed.forEach(function (name) {
    if (blocked.indexOf(name) >= 0) return;
    var selector = '[name="' + CSS.escape(name) + '"]';
    var el = document.querySelector(selector);
    if (!el || el.type === 'hidden') return;
    if ('value' in el) el.value = draft.fields[name];
  });
};
```

- 禁止使用 `` `#${name}` `` 选择器（避免特殊字符 / 与系统 id 冲突）
- 跳过 `type=hidden`、`:disabled`、`.csrf` 字段
- 不 submit；toast「已填充，请核对后保存」
- 仅当 `data-qms-module === draft.module` 且 action 为 add/edit 时启用按钮

### 6.3 page_meta 采集（send/create 必带）

`qms-copilot.js` 每次 POST 前调用：

```javascript
function qmsCollectPageMeta() {
  var body = document.body;
  return {
    controller: body.dataset.qmsController || '',
    action: body.dataset.qmsAction || '',
    record_id: body.dataset.qmsRecordId || '',
    route: body.dataset.qmsRoute || '',
    module: body.dataset.qmsModule || '',
    title: body.dataset.qmsTitle || ''
  };
}
```

FormData 追加：`page_meta[controller]`、`page_meta[action]` 等（ThinkPHP 数组接收）。

### 6.4 AI 配置页（ai_settings/index.html）

- 顶部 **生效来源** 徽章：`环境变量` / `数据库` / `未配置`
- env 覆盖时显示黄色 alert（见 §1.5）
- API Key 输入框（placeholder 显示掩码或「未配置」）；保存后若 env 存在，flash「已写入数据库（当前生效：环境变量）」
- 模型、Base URL（可选高级）
- 保留天数（默认 90）
- [保存] [测试连接] — 测试结果展示 `source` + `effective_masked_key`
- admin 区：[清空全部聊天会话]（需二次确认）

---

## 七、数据保留与清理

（与 v1 相同：90 天 + `php think ai:purge-chat`）

### 7.2 审计（P2 修订）

现有实现为 `AuditLog` 中间件写入 `histories` 表（`History::create`），**不是** `audit_logs`。

Phase 1 要求：

1. 扩展 `AuditLog.php` 的 `$logActions`：

```php
$logActions = [
    // ...existing
    'save',    // AiSettings/save
    'test',    // AiSettings/test
    'purge',   // AiChat/purge
    'send',    // 可选：AiChat/send 仅记元数据，不含 message 正文
];
```

2. `AiSettings/save` 的 History `details` 写 `AI settings updated`，**禁止**含 Key
3. `AiChat/purge` 的 details 写 `scope=all|mine, deleted=N`

---

## 八、安全约束

| 项 | 要求 |
|----|------|
| API Key | env 优先；DB 存 `v1:` AES-256-GCM 密文；`get()` 不解密 secret；仅 `resolveAiConfig` 内存持明文 |
| LLM 调用 | 仅服务端 curl |
| CSRF | 所有 copilot POST 带 `X-CSRF-TOKEN` + `__token__` |
| 会话隔离 | 查询/写入强制 `session_id + user_id + company_id` |
| 上下文数据 | 白名单 handler；强制 `company_id` |
| 表单草稿 | 服务端 `sanitizeDraft` + 前端 `allowed_fields` 双白名单；禁填系统字段 |
| Expert 模式 | Phase 1 只读摘要 |
| XSS | Markdown sanitize |

---

## 九、权限配置

（与 v1 相同）

---

## 十、Phase 划分

### Phase 1（本次实施）

- [ ] system_settings + ai_chat_* 三表
- [ ] SettingsCipher（AES-256-GCM v1 格式）
- [ ] AiSettingsService（get/getSecret 分离 + source UX）
- [ ] AiChatService（三重隔离 + send 时 pageContext + sanitizeDraft）
- [ ] PageContextBuilder + PageContext middleware + body data-qms-*
- [ ] qms-copilot.js（CSRF fetch + page_meta 随 send + applyDraft 白名单）
- [ ] AiSettings / AiChat 控制器
- [ ] AuditLog 扩展 save/test/purge
- [ ] 90 天 purge 命令
- [ ] AiAssistantService 改用 resolveAiConfig

### Phase 2 / 3

（与 v1 相同）

---

## 十一、Smoke Test 要点

`tests/qms_ai_chat_smoke.php`：

1. migration 三表存在
2. SettingsCipher 往返；DB 值 `v1:` 前缀；篡改 tag 解密失败
3. `get()` 对 secret 返回 null；`resolveAiConfig` 能取 Key
4. env 存在时 `resolveAiConfig()['source']==='env'`；DB 保存不改变 effective source
5. `sendMessage` 传入不同 `page_meta` 时 `context_snapshot.page.route` 随之变化
6. `assertSessionOwned`：跨 user/company 返回 404
7. `sanitizeDraft` 丢弃 `id`、`__token__`
8. `qms-copilot.js` 含 `X-CSRF-TOKEN` 与 `page_meta`
9. `main.html` 含 `data-qms-controller`
10. purge 命令删除超期 session
11. 路由、RBAC、FAB 条件渲染
12. `testConnection` 返回 `source` 字段

---

## 十二、预期效果

（与 v1 相同）

---

## 附录：与 AI 文档助理协作话术

（与 v1 相同）
