<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\DocumentPresentationService;
use app\service\NavigationPresentationService;

(new think\App())->initialize();

function lightweight_ui_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

lightweight_ui_assert(class_exists(NavigationPresentationService::class), '缺少日常/治理导航呈现服务');

$daily = NavigationPresentationService::context('Document', 'read', true, []);
$governance = NavigationPresentationService::context('QualityWorkbench', 'projects', true, []);
$history = NavigationPresentationService::context('Document', 'index', true, ['history' => '1']);
$staff = NavigationPresentationService::context('Capa', 'index', false, []);

lightweight_ui_assert($daily['layer'] === 'daily', '正文阅读必须使用日常层');
lightweight_ui_assert($governance['layer'] === 'governance', '评审项目必须使用治理层');
lightweight_ui_assert($history['layer'] === 'governance', '历史版本管理必须使用治理层');
lightweight_ui_assert($staff['layer'] === 'daily', '无治理权限用户不得得到治理导航');
lightweight_ui_assert($daily['can_govern'] === true, '有治理权限用户在日常层应获得进入质量管理入口');
lightweight_ui_assert(str_contains($daily['notice'], '纸质文件为正式依据'), '日常层必须使用唯一纸质边界文案');

lightweight_ui_assert(method_exists(DocumentPresentationService::class, 'businessNumber'), '缺少普通页面业务编号转换');
lightweight_ui_assert(method_exists(DocumentPresentationService::class, 'dailyStatusLabel'), '缺少普通页面状态转换');
lightweight_ui_assert(
    DocumentPresentationService::businessNumber('SIM-GOV03-XZTC/CX-08-2026') === 'CX-08',
    '普通页面必须把试装编号转换为业务编号'
);
lightweight_ui_assert(
    DocumentPresentationService::dailyStatusLabel('published') === '当前治理阅读版',
    '普通页面不得把测试库 published 显示成正式发布'
);

$layout = (string)file_get_contents(__DIR__ . '/../app/view/layout/main.html');
$index = (string)file_get_contents(__DIR__ . '/../app/view/document/index.html');
$reader = (string)file_get_contents(__DIR__ . '/../app/view/document/read.html');
$readerCss = (string)file_get_contents(__DIR__ . '/../public/static/css/qms-document-reader.css');
$navigationCss = (string)file_get_contents(__DIR__ . '/../public/static/css/qms-navigation.css');

foreach (['我的工作', '查文件', '查记录', '进入质量管理', '返回日常工作'] as $label) {
    lightweight_ui_assert(str_contains($layout, $label), '双层导航缺少：' . $label);
}
lightweight_ui_assert(str_contains($layout, "qmsNavigation.layer == 'daily'"), '布局必须按日常/治理层渲染');
lightweight_ui_assert(str_contains($layout, '纸质文件为正式依据 · 系统用于快速查阅与治理核对'), '布局缺少唯一运行依据提示');
lightweight_ui_assert(!str_contains($index, '8021 当前阅读版'), '文件库不得显示测试环境内部状态');
lightweight_ui_assert(!str_contains($reader, '试运行环境已发布登记'), '阅读页不得显示已发布登记');
lightweight_ui_assert(str_contains($reader, '<details class="qms-document-reader__relations"'), '链路必须使用无脚本可展开结构');
lightweight_ui_assert(str_contains($reader, '{$data.relationship_count}'), '链路入口必须显示关系数量');
lightweight_ui_assert(str_contains($readerCss, 'grid-template-columns: 210px minmax(0, 1fr)'), '阅读页必须改为目录加正文两栏');
lightweight_ui_assert(str_contains($navigationCss, '.qms-daily-boundary'), '缺少日常层统一提示样式');

echo "qms_lightweight_dual_layer_ui_smoke passed\n";
