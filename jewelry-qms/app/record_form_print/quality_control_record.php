<?php
use app\service\RecordFormPrintService as P;

$rows = P::rows($values, 'results');
if ($rows === []) {
    $rows = [['item' => '', 'expected' => '', 'actual' => '', 'judgement' => '']];
}
$docNumber = P::cell($template, 'doc_number');
$version = P::cell($template, 'version');
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title>内部质量监控记录表</title>
    <style>
        @page { size: A4; margin: 18mm 16mm; }
        body { font-family: "Noto Sans CJK SC", "Microsoft YaHei", Arial, sans-serif; color: #111; font-size: 12px; }
        .title { text-align: center; font-size: 20px; font-weight: 700; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #111; padding: 7px 8px; vertical-align: middle; word-break: break-word; }
        th { background: #f5f5f5; font-weight: 700; }
        <?= P::tablePaginationCss() ?>
    </style>
</head>
<body>
    <div class="title">内部质量监控记录表</div>
    <table>
        <tr><th>编号</th><td><?= $docNumber !== '' ? $docNumber : 'XZTC/BG-30-05' ?></td><th>版本</th><td><?= $version !== '' ? $version : 'A/0' ?></td></tr>
        <tr><th>监控日期</th><td><?= P::value($values, 'monitor_date') ?></td><th>监控类型</th><td><?= P::value($values, 'monitor_type') ?></td></tr>
        <tr><th>样品或项目信息</th><td colspan="3"><?= nl2br(P::value($values, 'sample_info')) ?></td></tr>
    </table>
    <table style="margin-top:10px">
        <tr><th>项目</th><th>预期或参考值</th><th>实测结果</th><th style="width:16%">判定</th></tr>
        <?php foreach ($rows as $row): ?>
        <tr><td><?= P::cell($row, 'item') ?></td><td><?= P::cell($row, 'expected') ?></td><td><?= P::cell($row, 'actual') ?></td><td><?= P::cell($row, 'judgement') ?></td></tr>
        <?php endforeach; ?>
    </table>
    <table style="margin-top:10px">
        <tr><th style="width:20%">后续措施</th><td><?= nl2br(P::value($values, 'follow_up')) ?></td></tr>
    </table>
</body>
</html>
