<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\DocumentPresentationService;
use app\service\NotificationPresentationService;
use app\service\QmsReadableMarkdownService;

function four_role_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$markdown = <<<'MD'
# 文件控制程序

> 本文件仅用于 8021 试运行。

## 工作程序

- 编制人起草
- 审核人审核

| 步骤 | 责任人 |
|---|---|
| 编制 | 文件管理员 |

<script>alert('xss')</script>
MD;

$html = QmsReadableMarkdownService::toHtml($markdown);
four_role_runtime_assert(str_contains($html, '<h1>文件控制程序</h1>'), '连续正文应渲染一级标题');
four_role_runtime_assert(str_contains($html, '<blockquote>本文件仅用于 8021 试运行。</blockquote>'), '连续正文应渲染提示引用');
four_role_runtime_assert(str_contains($html, '<ul>'), '连续正文应渲染无序清单');
four_role_runtime_assert(str_contains($html, '<table'), '连续正文应渲染表格');
four_role_runtime_assert(!str_contains($html, '<script>'), '连续正文必须转义原始 HTML');
four_role_runtime_assert(str_contains($html, '&lt;script&gt;'), '转义后的危险文本应保留为可读文字');

$governedDocument = <<<'MD'
# XZTC/CX-03-02-2022 标准物质管理程序

> **SIM｜治理试运行候选**

- 生成批次：GOV-TRIAL-20260725
- 文件状态：trial_ready
- 基线原件 SHA-256：abc123

---

# 标准物质管理程序

## 目的

确保标准物质处于受控状态。
MD;
$readableParts = QmsReadableMarkdownService::separateGovernancePreamble($governedDocument);
four_role_runtime_assert(
    str_contains((string)($readableParts['body'] ?? ''), '# 标准物质管理程序'),
    '业务阅读正文必须保留实际文件标题'
);
four_role_runtime_assert(
    !str_contains((string)($readableParts['body'] ?? ''), 'SHA-256'),
    '业务阅读正文不得显示内部哈希信息'
);
four_role_runtime_assert(
    str_contains((string)($readableParts['technical'] ?? ''), 'SHA-256'),
    '技术折叠区必须保留内部追溯信息'
);

$reason = DocumentPresentationService::changeReason(
    '{"notice":"依据冲突审查完成修订。","source_sha256":"abc123","trial_batch":"GOV-TRIAL-20260724"}'
);
four_role_runtime_assert(
    ($reason['summary'] ?? '') === '依据冲突审查完成修订。',
    'JSON 修订说明必须提取中文业务摘要'
);
four_role_runtime_assert(
    str_contains((string)($reason['technical'] ?? ''), 'source_sha256'),
    '原始技术信息必须保留以供追溯'
);

$structure = DocumentPresentationService::structureSummary([
    'status' => 'draft',
    'render_status' => 'rendered',
]);
four_role_runtime_assert(($structure['status_label'] ?? '') === '结构草稿', '结构状态必须中文化');
four_role_runtime_assert(($structure['render_status_label'] ?? '') === '已生成可阅读正文', '渲染状态必须中文化');

four_role_runtime_assert(
    DocumentPresentationService::onlyofficeAvailable('draft', 'uploads/a.docx', 'a.docx', true, 'http://onlyoffice'),
    '已配置的草稿 Word 附件应允许在线编辑'
);
four_role_runtime_assert(
    !DocumentPresentationService::onlyofficeAvailable('published', 'uploads/a.docx', 'a.docx', true, 'http://onlyoffice'),
    '已发布文件不得显示覆盖式在线编辑入口'
);
four_role_runtime_assert(
    !DocumentPresentationService::onlyofficeAvailable('draft', 'uploads/a.docx', 'a.docx', false, ''),
    '服务未配置时不得显示在线编辑入口'
);

four_role_runtime_assert(
    DocumentPresentationService::nextVersion('A/0', 0) === 'A/1',
    'A/0 文件发起修订时应预告 A/1'
);

$notification = NotificationPresentationService::present([
    'title' => '记录更正申请处理结果',
    'message' => '检测环境监控记录表的更正申请已通过。',
    'type' => 'general',
    'link_controller' => 'record_form_instance',
    'link_action' => 'view',
    'link_id' => 'record-id',
    'status' => 0,
]);
four_role_runtime_assert(($notification['type_label'] ?? '') === '业务通知', '通知类型必须中文化');
four_role_runtime_assert(($notification['action_label'] ?? '') === '查看并处理', '记录通知必须给出可执行动作');
four_role_runtime_assert(($notification['status_label'] ?? '') === '尚未查看', '未读状态必须使用用户语言');

echo "qms_four_role_chinese_ux_runtime_smoke passed\n";
