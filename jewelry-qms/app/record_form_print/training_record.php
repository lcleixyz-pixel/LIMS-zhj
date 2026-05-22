<?php
use app\service\RecordFormPrintService as P;

$attendees = P::rows($values, 'attendees');
if ($attendees === []) {
    $attendees = [['name' => '', 'department' => '', 'signature' => '']];
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($template['name'] ?? '人员培训记录表', ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        @page { size: A4; margin: 18mm 16mm; }
        body { font-family: "Noto Sans CJK SC", "Microsoft YaHei", Arial, sans-serif; color: #111; font-size: 12px; }
        .title { text-align: center; font-size: 20px; font-weight: 700; margin-bottom: 12px; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #111; padding: 7px 8px; vertical-align: middle; word-break: break-word; }
        th { background: #f5f5f5; font-weight: 700; }
        .label { width: 18%; }
        .signature { height: 46px; }
        .footer { display: flex; justify-content: space-between; margin-top: 10px; font-size: 11px; }
    </style>
</head>
<body>
    <div class="title"><?= htmlspecialchars($template['name'] ?? '人员培训记录表', ENT_QUOTES, 'UTF-8') ?></div>
    <div class="meta">
        <div>编号：<?= htmlspecialchars($template['doc_number'] ?? 'XZTC/BG-01-02', ENT_QUOTES, 'UTF-8') ?></div>
        <div style="text-align:right">版本：<?= htmlspecialchars($template['version'] ?? 'A/0', ENT_QUOTES, 'UTF-8') ?></div>
    </div>
    <table>
        <tr>
            <th class="label">培训日期</th>
            <td><?= P::value($values, 'training_date') ?></td>
            <th class="label">培训讲师</th>
            <td><?= P::value($values, 'trainer') ?></td>
        </tr>
        <tr>
            <th>培训主题</th>
            <td colspan="3"><?= P::value($values, 'training_topic') ?></td>
        </tr>
        <tr>
            <th>培训内容</th>
            <td colspan="3" style="height:80px"><?= nl2br(P::value($values, 'training_content')) ?></td>
        </tr>
    </table>
    <table style="margin-top:10px">
        <tr>
            <th style="width:10%">序号</th>
            <th>姓名</th>
            <th>部门</th>
            <th>签名</th>
        </tr>
        <?php foreach ($attendees as $index => $row): ?>
        <tr>
            <td style="text-align:center"><?= $index + 1 ?></td>
            <td><?= P::cell($row, 'name') ?></td>
            <td><?= P::cell($row, 'department') ?></td>
            <td class="signature"><?= P::cell($row, 'signature') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <table style="margin-top:10px">
        <tr>
            <th class="label">效果评价</th>
            <td style="height:70px"><?= nl2br(P::value($values, 'effect_evaluation')) ?></td>
        </tr>
    </table>
    <div class="footer">
        <span>系统生成记录，仅用于打印归档</span>
        <span>生成日期：<?= date('Y-m-d') ?></span>
    </div>
</body>
</html>
