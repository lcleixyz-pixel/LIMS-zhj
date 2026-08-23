<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

(new think\App())->initialize();

function document_reader_ui_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$routes = (string)file_get_contents(__DIR__ . '/../route/app.php');
$controller = (string)file_get_contents(__DIR__ . '/../app/controller/Document.php');
$index = (string)file_get_contents(__DIR__ . '/../app/view/document/index.html');
$reader = (string)file_get_contents(__DIR__ . '/../app/view/document/read.html');
$css = (string)file_get_contents(__DIR__ . '/../public/static/css/qms-document-reader.css');
$js = (string)file_get_contents(__DIR__ . '/../public/static/js/qms-document-reader.js');
$fileService = (string)file_get_contents(__DIR__ . '/../app/service/FileService.php');

foreach ([
    "document/read', 'Document/read",
    "document/source-download', 'Document/sourceDownload",
] as $route) {
    document_reader_ui_assert(str_contains($routes, $route), '缺少文件阅读接口：' . $route);
}
document_reader_ui_assert(str_contains($controller, 'function read'), '文件控制器必须提供统一正文阅读入口');
document_reader_ui_assert(str_contains($controller, 'function sourceDownload'), '文件控制器必须提供来源Word下载入口');
document_reader_ui_assert(str_contains($controller, 'DocumentReadingService'), '正文、目录和链路必须由专用阅读服务聚合');
document_reader_ui_assert(str_contains($controller, 'DocumentSourceAssetService'), '来源Word必须由受限来源资产服务解析');
document_reader_ui_assert(str_contains($controller, "redirect('/document/read?id='"), '旧候选预览必须重定向到统一阅读页');

document_reader_ui_assert(str_contains($index, '/document/read?id={$doc.id}'), '点击文件名称必须直接阅读正文');
document_reader_ui_assert(str_contains($index, '/document/source-download?id={$doc.id}'), '文件列表必须提供来源Word入口');
document_reader_ui_assert(str_contains($index, '搜索文件编号、名称或正文关键词'), '文件库必须提供一个主搜索框');
foreach (['全部', '质量手册', '程序文件', '作业指导书', '已作废'] as $label) {
    document_reader_ui_assert(str_contains($index, $label), '文件库缺少快捷分类：' . $label);
}

foreach (['治理中 · 纸质执行', '章节目录', '文件链路', '打印阅读稿（非受控）', '下载来源 Word（用 WPS 打开）', '查看完整链路'] as $label) {
    document_reader_ui_assert(str_contains($reader, $label), '阅读页缺少关键文案：' . $label);
}
document_reader_ui_assert(str_contains($reader, 'qms-document-reader__toc'), '阅读页必须有章节目录区域');
document_reader_ui_assert(str_contains($reader, 'qms-document-reader__content'), '阅读页必须有连续正文区域');
document_reader_ui_assert(str_contains($reader, 'qms-document-reader__relations'), '阅读页必须有链路侧栏');
document_reader_ui_assert(str_contains($css, 'grid-template-columns'), '阅读页必须使用稳定三栏网格');
document_reader_ui_assert(str_contains($css, ':focus-visible'), '文件阅读交互必须有键盘焦点提示');
document_reader_ui_assert(str_contains($js, 'data-document-search'), '页内搜索脚本必须绑定明确的数据属性');
document_reader_ui_assert(str_contains($fileService, "filename*=UTF-8''"), '中文来源文件名必须使用浏览器兼容下载头');
document_reader_ui_assert(!str_contains($reader, 'controlledPrint'), '治理阶段打印不得伪装成受控打印');

echo "qms_document_reader_ui_smoke passed\n";
