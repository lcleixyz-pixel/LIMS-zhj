<?php
use app\service\RecordFormPrintService as P;

$rows = P::rows($values, 'check_items');
if ($rows === []) {
    $rows = [['item' => '', 'method' => '', 'result' => '', 'conclusion' => '']];
}
$docNumber = P::cell($template, 'doc_number');
$version = P::cell($template, 'version');
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title>仪器设备和标准物质期间核查记录表</title>
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
    <div class="title">仪器设备和标准物质期间核查记录表</div>
    <table>
        <tr><th>编号</th><td><?= $docNumber !== '' ? $docNumber : 'XZTC/BG-04-03' ?></td><th>版本</th><td><?= $version !== '' ? $version : 'A/0' ?></td></tr>
        <tr><th>名称</th><td><?= P::value($values, 'equipment_name') ?></td><th>设备/标准物质编号</th><td><?= P::value($values, 'equipment_code') ?></td></tr>
        <tr><th>核查日期</th><td><?= P::value($values, 'check_date') ?></td><th>核查人</th><td><?= P::value($values, 'checker') ?></td></tr>
    </table>
    <table style="margin-top:10px">
        <tr><th style="width:8%">序号</th><th>核查项目</th><th>方法</th><th>结果</th><th>结论</th></tr>
        <?php foreach ($rows as $index => $row): ?>
        <tr><td><?= $index + 1 ?></td><td><?= P::cell($row, 'item') ?></td><td><?= P::cell($row, 'method') ?></td><td><?= P::cell($row, 'result') ?></td><td><?= P::cell($row, 'conclusion') ?></td></tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
