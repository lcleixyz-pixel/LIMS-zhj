<?php
use app\service\RecordFormPrintService as P;

$schema = $template['field_schema'] ?? [];
if (is_string($schema)) {
    $decoded = json_decode($schema, true);
    $schema = is_array($decoded) ? $decoded : [];
}
$docNumber = P::cell($template, 'doc_number');
$version = P::cell($template, 'version');
$name = P::cell($template, 'name');
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title><?= $name !== '' ? $name : '记录表格' ?></title>
    <style>
        @page { size: A4; margin: 18mm 16mm; }
        body { font-family: "Noto Sans CJK SC", "Microsoft YaHei", Arial, sans-serif; color: #111; font-size: 12px; }
        .title { text-align: center; font-size: 20px; font-weight: 700; margin-bottom: 12px; }
        .section-title { font-weight: 700; margin: 12px 0 6px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #111; padding: 7px 8px; vertical-align: middle; word-break: break-word; }
        th { background: #f5f5f5; font-weight: 700; }
        .label { width: 22%; }
        .footer { display: flex; justify-content: space-between; margin-top: 10px; font-size: 11px; }
        <?= P::tablePaginationCss() ?>
    </style>
</head>
<body>
    <div class="title"><?= $name !== '' ? $name : '记录表格' ?></div>
    <table>
        <tr>
            <th class="label">编号</th>
            <td><?= $docNumber ?></td>
            <th class="label">版本</th>
            <td><?= $version !== '' ? $version : 'A/0' ?></td>
        </tr>
    </table>

    <?php foreach ($schema as $field): ?>
        <?php if (($field['type'] ?? '') === 'repeatable_table'): ?>
            <?php
            $columns = $field['columns'] ?? [];
            $rows = P::rows($values, (string)$field['key']);
            if ($rows === []) {
                $rows = [array_fill_keys(array_column($columns, 'key'), '')];
            }
            ?>
            <div class="section-title"><?= P::cell($field, 'label') ?></div>
            <table>
                <tr>
                    <th style="width:8%">序号</th>
                    <?php foreach ($columns as $column): ?>
                    <th><?= P::cell($column, 'label') ?></th>
                    <?php endforeach; ?>
                </tr>
                <?php foreach ($rows as $index => $row): ?>
                <tr>
                    <td style="text-align:center"><?= $index + 1 ?></td>
                    <?php foreach ($columns as $column): ?>
                    <td><?= nl2br(P::cell($row, (string)$column['key'])) ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <table style="margin-top:8px">
                <tr>
                    <th class="label"><?= P::cell($field, 'label') ?></th>
                    <td><?= nl2br(P::value($values, (string)$field['key'])) ?></td>
                </tr>
            </table>
        <?php endif; ?>
    <?php endforeach; ?>

    <div class="footer">
        <span>系统生成记录，仅用于打印归档</span>
        <span>生成日期：<?= date('Y-m-d') ?></span>
    </div>
</body>
</html>
