<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn (string $path): string => (string)file_get_contents($root . '/' . $path);

function four_role_assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, '缺少：' . $needle . PHP_EOL);
        exit(1);
    }
}

function four_role_assert_not_contains(string $needle, string $haystack, string $message): void
{
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, '不应出现：' . $needle . PHP_EOL);
        exit(1);
    }
}

$dashboardController = $read('app/controller/Dashboard.php');
$dashboard = $read('app/view/dashboard/index.html');
$documentController = $read('app/controller/Document.php');
$documentIndex = $read('app/view/document/index.html');
$documentView = $read('app/view/document/view.html');
$resolvedArtifact = $read('app/view/planning_structure/resolved_artifact.html');
$notificationController = $read('app/controller/Notification.php');
$notificationView = $read('app/view/notification/index.html');
$recordIndex = $read('app/view/record_form_instance/index.html');
$recordCreate = $read('app/view/record_form_instance/create.html');
$revisionView = $read('app/view/document/revise.html');
$fieldAuditService = $read('app/service/FieldAuditService.php');
$routes = $read('route/app.php');

four_role_assert_contains(
    "'pendingReview' => \$pendingApprovals",
    $dashboardController,
    '技术负责人首页待审数字必须取当前用户待签数量'
);
four_role_assert_contains('待我签批', $dashboard, '首页必须使用当前用户能理解的“待我签批”');
four_role_assert_contains('pending_for_me=1', $dashboardController . $dashboard, '待签入口必须进入本人审批队列');
four_role_assert_contains(
    "(\$filter.status ?? '') == 'reviewing'",
    $documentIndex,
    '文件状态筛选必须用明确括号，避免错误回显为已作废'
);
four_role_assert_contains('当前试运行版本', $documentIndex . $documentView, '同编号版本必须给出当前试运行结论');
four_role_assert_contains('纸质文件仍为正式依据', $documentIndex . $documentView, '8021 文件页面必须持续显示正式体系边界');

four_role_assert_contains('content_html', $resolvedArtifact, '连续正文必须使用安全渲染后的 HTML');
four_role_assert_not_contains(
    '{$artifact.content|htmlspecialchars}</pre>',
    $resolvedArtifact,
    '连续正文不得整页输出原始 Markdown'
);
four_role_assert_contains('技术信息', $resolvedArtifact, '内部路径必须降级到技术信息区域');

four_role_assert_contains('NotificationPresentationService', $notificationController, '通知中心必须使用中文展示服务');
four_role_assert_contains('order(\'n.created\', \'desc\')', $notificationController, '通知默认必须按业务发生时间倒序');
four_role_assert_contains('{$item.action_label}', $notificationView, '通知必须提供清晰的唯一主操作');
four_role_assert_not_contains('{$item.type}', $notificationView, '通知不得直接显示内部英文类型');

four_role_assert_contains('<h4>已填记录</h4>', $recordIndex, '记录列表必须说明这里展示的是已填记录');
four_role_assert_contains('开始填一张新表', $recordIndex, '记录员必须看到低门槛的新建入口');
four_role_assert_contains('开始填一张新表', $dashboard, '工作台快速入口必须与记录页面使用相同的中文动作名称');
four_role_assert_contains('aria-label="{$column.label}', $recordCreate, '表格内每个输入格必须有中文可访问名称');
four_role_assert_contains('{$column.unit}', $recordCreate, '数值字段必须显示固定单位');
four_role_assert_contains('这张表还不能保存，请补充以下内容', $recordCreate, '校验失败时必须集中列出中文补充项');
four_role_assert_contains('data-required-when-field', $recordCreate, '条件必填字段必须向前端声明触发条件');

four_role_assert_contains('controlledPrintForm', $documentController, '受控打印必须先进入事前确认页');
four_role_assert_contains('QmsReadableMarkdownService::toHtml', $documentController, 'Markdown 打印正文必须调用真实存在的安全渲染方法');
four_role_assert_contains("document/controlledPrintForm", $routes, '受控打印准备页必须有独立路由');
four_role_assert_contains('onlyofficeAvailable', $documentView, '在线编辑入口必须受真实可用性控制');
four_role_assert_contains('拟生成版本', $revisionView, '发起修订必须预告新版本');
four_role_assert_contains('旧版本将完整保留', $revisionView, '发起修订必须解释旧版保留规则');
four_role_assert_contains(
    "DocumentPresentationService::statusLabel(\$text)",
    $fieldAuditService,
    '文件变更历史不得直接显示内部英文状态码'
);

echo "qms_four_role_chinese_ux_smoke passed\n";
