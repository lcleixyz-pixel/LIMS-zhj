<?php
use app\service\RecordFormPrintService as P;

$rows = P::rows($values, 'inputs');
if ($rows === []) {
    $rows = [['topic' => '', 'owner' => '', 'material' => '']];
}
$docNumber = P::cell($template, 'doc_number');
$version = P::cell($template, 'version');
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title>管理评审计划表</title>
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
    <div class="title">管理评审计划表</div>
    <table>
        <tr><th>编号</th><td><?= $docNumber !== '' ? $docNumber : 'XZTC/BG-21-01' ?></td><th>版本</th><td><?= $version !== '' ? $version : 'A/0' ?></td></tr>
        <tr><th>评审年度</th><td><?= P::value($values, 'review_year') ?></td><th>会议日期</th><td><?= P::value($values, 'meeting_date') ?></td></tr>
        <tr><th>主持人</th><td><?= P::value($values, 'host') ?></td><th>参加人员</th><td><?= nl2br(P::value($values, 'participants')) ?></td></tr>
    </table>
    <table style="margin-top:10px">
        <tr><th>输入主题</th><th style="width:18%">责任人</th><th>资料要求</th></tr>
        <?php foreach ($rows as $row): ?>
        <tr><td><?= P::cell($row, 'topic') ?></td><td><?= P::cell($row, 'owner') ?></td><td><?= nl2br(P::cell($row, 'material')) ?></td></tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
