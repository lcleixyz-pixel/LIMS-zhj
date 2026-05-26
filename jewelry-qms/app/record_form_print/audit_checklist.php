<?php
use app\service\RecordFormPrintService as P;

$rows = P::rows($values, 'check_items');
if ($rows === []) {
    $rows = [['clause' => '', 'requirement' => '', 'evidence' => '', 'result' => '']];
}
$docNumber = P::cell($template, 'doc_number');
$version = P::cell($template, 'version');
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title>现场检测能力审核记录表</title>
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
    <div class="title">现场检测能力审核记录表</div>
    <table>
        <tr><th>编号</th><td><?= $docNumber !== '' ? $docNumber : 'XZTC/BG-20-07' ?></td><th>版本</th><td><?= $version !== '' ? $version : 'A/0' ?></td></tr>
        <tr><th>审核日期</th><td><?= P::value($values, 'audit_date') ?></td><th>受审核部门</th><td><?= P::value($values, 'audited_department') ?></td></tr>
        <tr><th>审核员</th><td colspan="3"><?= P::value($values, 'auditor') ?></td></tr>
    </table>
    <table style="margin-top:10px">
        <tr><th style="width:12%">条款</th><th>检查要求</th><th>审核证据</th><th style="width:14%">结果</th></tr>
        <?php foreach ($rows as $row): ?>
        <tr><td><?= P::cell($row, 'clause') ?></td><td><?= nl2br(P::cell($row, 'requirement')) ?></td><td><?= nl2br(P::cell($row, 'evidence')) ?></td><td><?= P::cell($row, 'result') ?></td></tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
