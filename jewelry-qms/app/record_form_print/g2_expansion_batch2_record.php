<?php
use app\service\RecordFormPrintService as P;

$displayDocNumber = htmlspecialchars(P::displayDocNumber($template, $values), ENT_QUOTES, 'UTF-8');
$masterDocNumber = htmlspecialchars((string)($template['doc_number'] ?? ''), ENT_QUOTES, 'UTF-8');
$templateName = htmlspecialchars((string)($template['name'] ?? 'G2扩2批记录表'), ENT_QUOTES, 'UTF-8');
$version = htmlspecialchars((string)($template['version'] ?? 'A/0'), ENT_QUOTES, 'UTF-8');
$module = htmlspecialchars((string)($template['module'] ?? ''), ENT_QUOTES, 'UTF-8');
$retention = htmlspecialchars((string)($template['retention'] ?? '不少于6年'), ENT_QUOTES, 'UTF-8');
$schema = $template['field_schema'] ?? [];
$noteLines = array_values(array_filter([
    (string)($template['master_note'] ?? ''),
    (string)($template['prefill_issue_note'] ?? ''),
    (string)($template['migration_note'] ?? ''),
    (string)($template['blocking_note'] ?? ''),
    (string)($template['alias_note'] ?? ''),
]));
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title><?= $templateName ?></title>
    <style>
        @page { size: A4; margin: 15mm 13mm; }
        body { font-family: "Noto Sans CJK SC", "Microsoft YaHei", Arial, sans-serif; color: #111; font-size: 12px; line-height: 1.42; }
        .title { text-align: center; font-size: 20px; font-weight: 700; margin: 2px 0 10px; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 18px; margin-bottom: 8px; font-size: 11px; }
        .note { border: 1px solid #777; padding: 6px 8px; margin: 8px 0; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 6px; }
        th, td { border: 1px solid #111; padding: 6px 7px; vertical-align: middle; word-break: break-word; }
        th { background: #f4f4f4; font-weight: 700; text-align: center; }
        .label { width: 22%; }
        .textarea { min-height: 54px; }
        .footer { display: flex; justify-content: space-between; margin-top: 9px; font-size: 10.5px; }
        <?= P::tablePaginationCss() ?>
    </style>
</head>
<body>
    <div class="title"><?= $templateName ?></div>
    <div class="meta">
        <div>编号：<?= $displayDocNumber ?><?php if ($displayDocNumber !== $masterDocNumber): ?>（母版：<?= $masterDocNumber ?>）<?php endif; ?></div>
        <div style="text-align:right">版次：<?= $version ?>　保存期限：<?= $retention ?></div>
        <div>归属程序：<?= $module ?></div>
        <div style="text-align:right">状态：G2扩2批候选蓝图沙箱样张</div>
    </div>
    <?php if ($noteLines !== []): ?>
    <div class="note">
        <?php foreach ($noteLines as $line): ?>
            <div><?= htmlspecialchars($line, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php foreach ($schema as $field): ?>
        <?php if (($field['type'] ?? '') === 'repeatable_table'): ?>
            <?php
            $columns = $field['columns'] ?? [];
            $rows = P::rows($values, (string)$field['key']);
            if ($rows === []) {
                $rows = [[]];
            }
            ?>
            <div style="font-weight:700;margin-top:8px"><?= htmlspecialchars((string)$field['label'], ENT_QUOTES, 'UTF-8') ?></div>
            <table>
                <thead>
                <tr>
                    <th style="width:7%">序号</th>
                    <?php foreach ($columns as $column): ?>
                        <th><?= htmlspecialchars((string)$column['label'], ENT_QUOTES, 'UTF-8') ?></th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $index => $row): ?>
                    <tr>
                        <td style="text-align:center"><?= $index + 1 ?></td>
                        <?php foreach ($columns as $column): ?>
                            <td><?= P::cell($row, (string)$column['key']) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <table>
                <tr>
                    <th class="label"><?= htmlspecialchars((string)$field['label'], ENT_QUOTES, 'UTF-8') ?></th>
                    <td class="<?= ($field['type'] ?? '') === 'textarea' ? 'textarea' : '' ?>"><?= nl2br(P::value($values, (string)$field['key'])) ?></td>
                </tr>
            </table>
        <?php endif; ?>
    <?php endforeach; ?>

    <div class="footer">
        <span>候选待人审；单一母版；XZTC/XZTCH 双号呈现；正式启用另走签认与切换登记</span>
        <span>生成日期：<?= date('Y-m-d') ?></span>
    </div>
</body>
</html>
