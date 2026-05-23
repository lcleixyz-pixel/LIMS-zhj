<?php
use app\service\RecordFormPrintService as P;

$rawDocNumber = (string)($template['doc_number'] ?? '');
$rawPrintKey = (string)($template['print_template_key'] ?? '');
if (preg_match('/\AXZTC\/BG-03-0[1-9]\z/', $rawDocNumber) === 1 && str_starts_with($rawPrintKey, 'rf_xztc_bg_03_')) {
    $equipmentPrintTemplate = match ($rawDocNumber) {
        'XZTC/BG-03-01' => 'equipment_register',
        'XZTC/BG-03-02' => 'equipment_usage',
        'XZTC/BG-03-03' => 'equipment_maintenance',
        'XZTC/BG-03-04' => 'equipment_repair',
        'XZTC/BG-03-05' => 'equipment_acceptance',
        'XZTC/BG-03-06' => 'equipment_downgrade',
        'XZTC/BG-03-07' => 'equipment_scrap_seal',
        'XZTC/BG-03-08' => 'equipment_history',
        'XZTC/BG-03-09' => 'field_performance',
        default => '',
    };
    require __DIR__ . '/_equipment_record_forms.php';
    return;
}
if (preg_match('/\AXZTC\/BG-04-0[1-6]\z/', $rawDocNumber) === 1 && str_starts_with($rawPrintKey, 'rf_xztc_bg_04_')) {
    $equipmentPrintTemplate = match ($rawDocNumber) {
        'XZTC/BG-04-01' => 'period_plan',
        'XZTC/BG-04-02' => 'period_scheme',
        'XZTC/BG-04-03' => 'period_record',
        'XZTC/BG-04-04' => 'function_plan',
        'XZTC/BG-04-05' => 'function_record',
        'XZTC/BG-04-06' => 'period_report',
        default => '',
    };
    require __DIR__ . '/_equipment_record_forms.php';
    return;
}
if (preg_match('/\AXZTC\/BG-08-0[1-9]\z/', $rawDocNumber) === 1 && str_starts_with($rawPrintKey, 'rf_xztc_bg_08_')) {
    $fileControlPrintTemplate = match ($rawDocNumber) {
        'XZTC/BG-08-01' => 'controlled_file_register',
        'XZTC/BG-08-02' => 'external_file_register',
        'XZTC/BG-08-03' => 'distribution_recovery',
        'XZTC/BG-08-04' => 'borrow_register',
        'XZTC/BG-08-05' => 'replacement_request',
        'XZTC/BG-08-06' => 'change_approval',
        'XZTC/BG-08-07' => 'destruction_record',
        'XZTC/BG-08-08' => 'meeting_sign_in',
        'XZTC/BG-08-09' => 'sample_original_record',
        default => '',
    };
    require __DIR__ . '/_file_control_record_forms.php';
    return;
}

$schema = $template['field_schema'] ?? [];
if (is_string($schema)) {
    $decoded = json_decode($schema, true);
    $schema = is_array($decoded) ? $decoded : [];
}
$docNumber = P::cell($template, 'doc_number');
$version = P::cell($template, 'version');
$name = P::cell($template, 'name');
$module = P::cell($template, 'module');
$sourceFileName = P::cell($template, 'source_file_name');
$reference = P::cell($template, 'reference');
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title><?= $name !== '' ? $name : '记录表格' ?></title>
    <style>
        @page { size: A4; margin: 16mm 14mm; }
        body { font-family: "Noto Sans CJK SC", "Microsoft YaHei", Arial, sans-serif; color: #111; font-size: 12px; line-height: 1.45; }
        .draft-mark { text-align: center; color: #8a5a00; font-size: 11px; margin-bottom: 4px; }
        .title { text-align: center; font-size: 20px; font-weight: 700; margin-bottom: 10px; }
        .meta { margin-bottom: 10px; }
        .section-title { font-weight: 700; margin: 12px 0 6px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #111; padding: 6px 7px; vertical-align: middle; word-break: break-word; }
        th { background: #f5f5f5; font-weight: 700; }
        .label { width: 22%; }
        .value-cell { min-height: 26px; }
        .textarea-cell { min-height: 54px; }
        .footer { display: flex; justify-content: space-between; gap: 12px; margin-top: 10px; font-size: 11px; color: #444; }
        <?= P::tablePaginationCss() ?>
    </style>
</head>
<body>
    <div class="draft-mark">高保真重构草稿：请与原始附件逐表复核后再开放正式填写</div>
    <div class="title"><?= $name !== '' ? $name : '记录表格' ?></div>
    <table class="meta">
        <tr>
            <th class="label">编号</th>
            <td><?= $docNumber ?></td>
            <th class="label">版本</th>
            <td><?= $version !== '' ? $version : 'A/0' ?></td>
        </tr>
        <tr>
            <th>归属模块</th>
            <td><?= $module ?></td>
            <th>原始附件</th>
            <td><?= $sourceFileName ?></td>
        </tr>
        <?php if ($reference !== ''): ?>
        <tr>
            <th>参考对应项</th>
            <td colspan="3"><?= $reference ?></td>
        </tr>
        <?php endif; ?>
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
                <thead>
                    <tr>
                        <th style="width:8%">序号</th>
                        <?php foreach ($columns as $column): ?>
                        <th><?= P::cell($column, 'label') ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $index => $row): ?>
                    <tr>
                        <td style="text-align:center"><?= $index + 1 ?></td>
                        <?php foreach ($columns as $column): ?>
                        <td><?= nl2br(P::cell($row, (string)$column['key'])) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <?php $cellClass = (($field['type'] ?? '') === 'textarea') ? 'textarea-cell' : 'value-cell'; ?>
            <table style="margin-top:8px">
                <tr>
                    <th class="label"><?= P::cell($field, 'label') ?></th>
                    <td class="<?= $cellClass ?>"><?= nl2br(P::value($values, (string)$field['key'])) ?></td>
                </tr>
            </table>
        <?php endif; ?>
    <?php endforeach; ?>

    <div class="footer">
        <span>系统重构打印预览，正式启用前应完成逐表版式复核</span>
        <span>生成日期：<?= date('Y-m-d') ?></span>
    </div>
</body>
</html>
