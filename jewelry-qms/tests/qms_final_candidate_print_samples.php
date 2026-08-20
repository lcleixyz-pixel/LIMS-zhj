<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\FinalCandidateAssemblyService;
use app\service\QmsReadableMarkdownService;
use think\facade\Db;

(new think\App())->initialize();

$outputDir = rtrim((string)($argv[1] ?? ''), '/');
if ($outputDir === '') {
    fwrite(STDERR, "用法：php tests/qms_final_candidate_print_samples.php <输出目录>\n");
    exit(1);
}
if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "无法创建输出目录\n");
    exit(1);
}

$samples = [
    '质量手册' => 'SIM-GOV03-XZTC/SC-2026',
    'CX-08' => 'SIM-GOV03-XZTC/CX-08-2026',
    'CX-26' => 'SIM-GOV03-XZTC/CX-26-2026',
    'ZY-1-01' => 'SIM-GOV03-XZTC/ZY-1-01-2026',
    'ZY-4-01' => 'SIM-GOV03-XZTC/ZY-4-01-2026',
];

$generated = [];
foreach ($samples as $label => $docNumber) {
    $document = Db::name('documents')
        ->where('doc_number', $docNumber)
        ->where('version', 'GOV-TRIAL/0.3')
        ->where('soft_delete', 0)
        ->find();
    if (!is_array($document)) {
        fwrite(STDERR, $docNumber . " 不存在\n");
        exit(1);
    }
    $sourcePath = FinalCandidateAssemblyService::resolveOutputPath((string)$document['file_path']);
    $markdown = $sourcePath === null ? false : file_get_contents($sourcePath);
    if (!is_string($markdown)) {
        fwrite(STDERR, $docNumber . " 正文不可读\n");
        exit(1);
    }

    $title = htmlspecialchars((string)$document['title'], ENT_QUOTES, 'UTF-8');
    $number = htmlspecialchars((string)$document['doc_number'], ENT_QUOTES, 'UTF-8');
    $status = htmlspecialchars((string)$document['status'], ENT_QUOTES, 'UTF-8');
    $body = QmsReadableMarkdownService::toHtml($markdown);
    $html = '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8">'
        . '<title>' . $title . '</title><style>'
        . '@page{size:A4;margin:18mm 16mm}body{font:14px/1.65 -apple-system,BlinkMacSystemFont,"PingFang SC",sans-serif;color:#222}'
        . 'header{border-bottom:2px solid #991b1b;margin-bottom:16px;padding-bottom:10px}.notice{border:1px solid #ef4444;background:#fef2f2;padding:10px;margin:12px 0}'
        . '.watermark{position:fixed;top:43%;left:9%;font-size:54px;font-weight:700;color:rgba(153,27,27,.12);transform:rotate(-24deg);z-index:99;white-space:nowrap}'
        . 'table{border-collapse:collapse;width:100%}th,td{border:1px solid #aaa;padding:5px}pre{white-space:pre-wrap}h1{font-size:24px}h2{font-size:19px;page-break-after:avoid}'
        . '</style></head><body><div class="watermark">8021 候选试装 · 非正式</div>'
        . '<header><strong>' . $title . '</strong><br><small>' . $number . ' · GOV-TRIAL/0.3 · ' . $status . '</small></header>'
        . '<div class="notice"><strong>8021 候选试装，非正式文件。</strong> 纸质体系仍为唯一正式体系；本打印件不得用于审核、发布或正式运行。</div>'
        . $body . '</body></html>';
    $htmlPath = $outputDir . '/' . $label . '-候选预览-v0.1.html';
    if (file_put_contents($htmlPath, $html) === false) {
        fwrite(STDERR, $htmlPath . " 写入失败\n");
        exit(1);
    }
    $generated[] = $htmlPath;
}

echo json_encode(['generated' => $generated], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
