<?php
use app\service\RecordFormPrintService as P;

$kind = $equipmentPrintTemplate ?? '';
$templateName = htmlspecialchars((string)($template['name'] ?? '仪器设备记录表格'), ENT_QUOTES, 'UTF-8');
$docNumber = htmlspecialchars((string)($template['doc_number'] ?? ''), ENT_QUOTES, 'UTF-8');
$version = htmlspecialchars((string)($template['version'] ?? 'A/0'), ENT_QUOTES, 'UTF-8');
$wideKinds = ['equipment_register', 'equipment_usage', 'field_performance', 'period_plan', 'function_plan'];
$pageSize = in_array($kind, $wideKinds, true) ? 'A4 landscape' : 'A4';
$schema = $template['field_schema'] ?? [];
if (is_string($schema)) {
    $decoded = json_decode($schema, true);
    $schema = is_array($decoded) ? $decoded : [];
}
$defaults = [];
foreach ($schema as $field) {
    if (($field['type'] ?? '') !== 'repeatable_table') {
        $defaults[(string)($field['key'] ?? '')] = (string)($field['default'] ?? '');
    }
}
$v = static fn (string $key, string $default = ''): string => P::value($values, $key, $defaults[$key] ?? $default);
$text = static fn (string $key, string $default = ''): string => nl2br(P::value($values, $key, $defaults[$key] ?? $default));
$cell = static fn (array $row, string $key): string => P::cell($row, $key);
$cellText = static fn (array $row, string $key): string => nl2br(P::cell($row, $key));
$rows = static function (string $key, int $minRows = 1) use ($values): array {
    $rows = P::rows($values, $key);
    while (count($rows) < $minRows) {
        $rows[] = [];
    }

    return $rows;
};
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title><?= $templateName ?></title>
    <style>
        @page { size: <?= $pageSize ?>; margin: 15mm 13mm; }
        body { font-family: "Noto Sans CJK SC", "Microsoft YaHei", Arial, sans-serif; color: #111; font-size: 12px; }
        .title { text-align: center; font-size: 20px; font-weight: 700; margin: 2px 0 11px; }
        .meta { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 11px; }
        .section-title { font-weight: 700; margin: 10px 0 5px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #111; padding: 6px 7px; vertical-align: middle; word-break: break-word; }
        th { background: #f4f4f4; font-weight: 700; text-align: center; }
        .label { width: 16%; background: #f4f4f4; font-weight: 700; text-align: center; }
        .center { text-align: center; }
        .small { font-size: 10.5px; }
        .tall { height: 70px; }
        .signature { height: 42px; }
        .footer { display: flex; justify-content: space-between; margin-top: 9px; font-size: 10.5px; }
        <?= P::tablePaginationCss() ?>
    </style>
</head>
<body>
    <div class="title"><?= $templateName ?></div>
    <div class="meta">
        <span>编号：<?= $docNumber ?></span>
        <span>版本：<?= $version ?></span>
    </div>

    <?php if ($kind === 'equipment_register'): ?>
        <table class="small">
            <thead>
            <tr>
                <th style="width:4%">序号</th>
                <th style="width:10%">编号</th>
                <th style="width:11%">名称</th>
                <th style="width:10%">规格型号</th>
                <th>生产厂</th>
                <th style="width:10%">出厂编号</th>
                <th style="width:9%">购进日期</th>
                <th style="width:14%">扩展不确定度/最大允差/准确度等级</th>
                <th style="width:9%">测量范围</th>
                <th style="width:8%">溯源方式</th>
                <th style="width:8%">备注</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows('equipment_items', 10) as $index => $row): ?>
            <tr>
                <td class="center"><?= $index + 1 ?></td>
                <td><?= $cell($row, 'equipment_code') ?></td>
                <td><?= $cell($row, 'equipment_name') ?></td>
                <td><?= $cell($row, 'model_spec') ?></td>
                <td><?= $cell($row, 'manufacturer') ?></td>
                <td><?= $cell($row, 'factory_number') ?></td>
                <td><?= $cell($row, 'purchase_date') ?></td>
                <td><?= $cell($row, 'accuracy') ?></td>
                <td><?= $cell($row, 'measurement_range') ?></td>
                <td><?= $cell($row, 'traceability_method') ?></td>
                <td><?= $cell($row, 'remarks') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="section-title small">填表说明：溯源方式栏应注明送校、自校、送检、自检、比对或其他验证方式。</div>

    <?php elseif ($kind === 'equipment_usage'): ?>
        <table>
            <tr>
                <th class="label">仪器名称</th><td><?= $v('equipment_name') ?></td>
                <th class="label">设备编号</th><td><?= $v('equipment_code') ?></td>
                <th class="label">年度</th><td><?= $v('usage_year') ?></td>
            </tr>
        </table>
        <table style="margin-top:8px" class="small">
            <thead>
            <tr>
                <th rowspan="2" style="width:5%">序号</th>
                <th colspan="2">日期</th>
                <th colspan="2">使用时间</th>
                <th colspan="2">仪器使用性能</th>
                <th rowspan="2" style="width:12%">使用人</th>
                <th rowspan="2">备注</th>
            </tr>
            <tr><th>月</th><th>日</th><th>开始</th><th>停止</th><th>使用前</th><th>使用后</th></tr>
            </thead>
            <tbody>
            <?php foreach ($rows('usage_items', 12) as $index => $row): ?>
            <tr>
                <td class="center"><?= $index + 1 ?></td>
                <td><?= $cell($row, 'month') ?></td><td><?= $cell($row, 'day') ?></td>
                <td><?= $cell($row, 'start_time') ?></td><td><?= $cell($row, 'end_time') ?></td>
                <td><?= $cell($row, 'before_status') ?></td><td><?= $cell($row, 'after_status') ?></td>
                <td><?= $cell($row, 'user') ?></td><td><?= $cell($row, 'remarks') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="section-title small">说明：在“正常”“异常”下记录即可。</div>

    <?php elseif ($kind === 'equipment_maintenance'): ?>
        <table>
            <tr><th class="label">仪器</th><td><?= $v('equipment_name') ?></td><th class="label">编号</th><td><?= $v('equipment_code') ?></td></tr>
        </table>
        <table style="margin-top:8px">
            <tr><th style="width:18%">时间</th><th style="width:22%">保养维护人</th><th>保养维护情况</th></tr>
            <?php foreach ($rows('maintenance_items', 10) as $row): ?>
            <tr><td><?= $cell($row, 'maintenance_time') ?></td><td><?= $cell($row, 'maintainer') ?></td><td><?= $cellText($row, 'maintenance_content') ?></td></tr>
            <?php endforeach; ?>
        </table>

    <?php elseif ($kind === 'equipment_repair'): ?>
        <table>
            <tr><th class="label">仪器设备名称</th><td><?= $v('equipment_name') ?></td><th class="label">设备编号</th><td><?= $v('equipment_code') ?></td></tr>
            <tr><th class="label">规格型号</th><td><?= $v('model_spec') ?></td><th class="label">购置日期</th><td><?= $v('purchase_date') ?></td></tr>
            <tr><th class="label">故障描述</th><td colspan="3" class="tall"><?= $text('failure_description') ?><br><br>操作人：<?= $v('operator') ?>　日期：<?= $v('operation_date') ?></td></tr>
            <tr><th class="label">维修方式及费用</th><td colspan="3" class="tall"><?= $text('repair_method_cost') ?><br><br>检测员：<?= $v('inspector') ?>　日期：<?= $v('inspection_date') ?></td></tr>
            <tr><th class="label">技术负责人审核意见</th><td colspan="3" class="tall"><?= $text('technical_manager_opinion') ?><br><br>签名/日期：<?= $v('technical_manager_date') ?></td></tr>
            <tr><th class="label">实验室主任审批</th><td colspan="3" class="tall"><?= $text('lab_director_approval') ?><br><br>签名/日期：<?= $v('lab_director_date') ?></td></tr>
        </table>

    <?php elseif ($kind === 'equipment_acceptance'): ?>
        <table>
            <tr><th class="label">名称及编号</th><td><?= $v('equipment_name_code') ?></td><th class="label">维修、租借日期</th><td><?= $v('repair_rental_date') ?></td></tr>
            <tr><th class="label">型号</th><td><?= $v('model_spec') ?></td><th class="label">接收日期</th><td><?= $v('receipt_date') ?></td></tr>
            <tr><th class="label">制造厂</th><td><?= $v('manufacturer') ?></td><th class="label">服务商/单位</th><td><?= $v('service_provider') ?></td></tr>
        </table>
        <table style="margin-top:8px">
            <tr><th style="width:24%">项目</th><th>情况记录</th></tr>
            <?php foreach ($rows('acceptance_items', 4) as $row): ?>
            <tr><td><?= $cell($row, 'item') ?></td><td><?= $cellText($row, 'record') ?></td></tr>
            <?php endforeach; ?>
        </table>
        <table style="margin-top:8px">
            <tr><th class="label">是否需要重新检定（校准）</th><td><?= $v('recalibration_needed') ?></td><th class="label">验收意见</th><td><?= $v('acceptance_result') ?></td></tr>
            <tr><th class="label">参加验收人员签名</th><td><?= $text('participants') ?></td><th class="label">所属部门</th><td><?= $v('department') ?></td></tr>
            <tr><th class="label">备注</th><td colspan="3" class="tall"><?= $text('remarks') ?></td></tr>
        </table>

    <?php elseif ($kind === 'equipment_downgrade'): ?>
        <table>
            <tr><th class="label">仪器设备名称</th><td><?= $v('equipment_name') ?></td><th class="label">仪器设备编号</th><td><?= $v('equipment_code') ?></td></tr>
            <tr><th class="label">规格型号</th><td><?= $v('model_spec') ?></td><th class="label">维修日期</th><td><?= $v('repair_date') ?></td></tr>
            <tr><th class="label">申请部门</th><td><?= $v('application_department') ?></td><th class="label">申请人</th><td><?= $v('applicant') ?></td></tr>
            <tr><th class="label">降级使用原因及现实精度</th><td colspan="3" class="tall"><?= $text('downgrade_reason_accuracy') ?></td></tr>
        </table>
        <table style="margin-top:8px">
            <tr><th>项目</th><th>是否符合规范要求</th><th>说明</th></tr>
            <?php foreach ($rows('requirement_checks', 4) as $row): ?>
            <tr><td><?= $cell($row, 'item') ?></td><td><?= $cell($row, 'conclusion') ?></td><td><?= $cell($row, 'remarks') ?></td></tr>
            <?php endforeach; ?>
        </table>
        <table style="margin-top:8px">
            <tr><th class="label">降级使用项目精度等要求</th><td colspan="3" class="tall"><?= $text('downgrade_requirements') ?></td></tr>
            <tr><th class="label">检测员意见</th><td><?= $text('inspector_opinion') ?><br><?= $v('inspector') ?>　<?= $v('inspector_date') ?></td><th class="label">技术负责人确认意见</th><td><?= $text('technical_confirmation') ?><br><?= $v('technical_manager') ?>　<?= $v('technical_manager_date') ?></td></tr>
            <tr><th class="label">实验室主任审批意见</th><td colspan="3"><?= $text('lab_director_approval') ?><br><?= $v('lab_director') ?>　<?= $v('lab_director_date') ?></td></tr>
        </table>

    <?php elseif ($kind === 'equipment_scrap_seal'): ?>
        <table>
            <tr><th class="label">设备名称</th><td><?= $v('equipment_name') ?></td><th class="label">设备编号</th><td><?= $v('equipment_code') ?></td></tr>
            <tr><th class="label">规格型号</th><td><?= $v('model_spec') ?></td><th class="label">购置日期</th><td><?= $v('purchase_date') ?></td></tr>
            <tr><th class="label">处理情况</th><td><?= $v('handling_status') ?></td><th class="label">金额（元）</th><td><?= $v('amount') ?></td></tr>
            <tr><th class="label">报废/封存</th><td><?= $v('action_type') ?></td><th class="label">设备管理员/日期</th><td><?= $v('equipment_admin') ?>　<?= $v('equipment_admin_date') ?></td></tr>
            <tr><th class="label">报废/封存原因及技术状况</th><td colspan="3" class="tall"><?= $text('reason_and_status') ?></td></tr>
            <tr><th class="label">技术负责人审核意见</th><td><?= $text('technical_manager_opinion') ?><br><?= $v('technical_manager_date') ?></td><th class="label">实验室主任审批意见</th><td><?= $text('lab_director_approval') ?><br><?= $v('lab_director_date') ?></td></tr>
            <tr><th class="label">备注</th><td colspan="3"><?= $text('remarks') ?></td></tr>
        </table>

    <?php elseif ($kind === 'equipment_history'): ?>
        <table class="small">
            <tr><th class="label">设备名称</th><td><?= $v('equipment_name') ?></td><th class="label">设备编号</th><td><?= $v('equipment_code') ?></td></tr>
            <tr><th class="label">供应商名称</th><td><?= $v('supplier_name') ?></td><th class="label">合同编号</th><td><?= $v('contract_number') ?></td></tr>
            <tr><th class="label">规格型号</th><td><?= $v('model_spec') ?></td><th class="label">出厂日期</th><td><?= $v('manufacture_date') ?></td></tr>
            <tr><th class="label">接收日期</th><td><?= $v('received_date') ?></td><th class="label">启用日期</th><td><?= $v('started_date') ?></td></tr>
            <tr><th class="label">存放地点</th><td><?= $v('storage_location') ?></td><th class="label">说明书编号</th><td><?= $v('manual_number') ?></td></tr>
            <tr><th class="label">接收状态</th><td><?= $v('received_status') ?></td><th class="label">维护方式</th><td><?= $v('maintenance_method') ?></td></tr>
            <tr><th class="label">校准/检定方式</th><td colspan="3"><?= $v('calibration_method') ?></td></tr>
        </table>
        <div class="section-title">校准/检定记录</div>
        <table class="small">
            <tr><th>校准/检定日期</th><th>有效期</th><th>证书编号</th><th>备注</th></tr>
            <?php foreach ($rows('calibration_items', 6) as $row): ?>
            <tr><td><?= $cell($row, 'calibration_date') ?></td><td><?= $cell($row, 'valid_until') ?></td><td><?= $cell($row, 'certificate_number') ?></td><td><?= $cell($row, 'remarks') ?></td></tr>
            <?php endforeach; ?>
        </table>

    <?php elseif ($kind === 'field_performance'): ?>
        <table>
            <tr><th class="label">设备名称</th><td><?= $v('equipment_name') ?></td><th class="label">设备编号</th><td><?= $v('equipment_code') ?></td></tr>
        </table>
        <table style="margin-top:8px" class="small">
            <tr><th style="width:5%">序号</th><th>使用日期</th><th>检测项目</th><th>运行时间</th><th>运回时间</th><th>运回性能</th><th>使用人</th><th>备注</th></tr>
            <?php foreach ($rows('performance_items', 12) as $index => $row): ?>
            <tr><td class="center"><?= $index + 1 ?></td><td><?= $cell($row, 'use_date') ?></td><td><?= $cell($row, 'test_item') ?></td><td><?= $cell($row, 'run_time') ?></td><td><?= $cell($row, 'return_time') ?></td><td><?= $cell($row, 'return_performance') ?></td><td><?= $cell($row, 'user') ?></td><td><?= $cell($row, 'remarks') ?></td></tr>
            <?php endforeach; ?>
        </table>

    <?php elseif ($kind === 'period_plan' || $kind === 'function_plan'): ?>
        <table>
            <tr><th style="width:7%">序号</th><th>被核查仪器设备或标准物质名称和编号</th><th style="width:18%">核查计划实施时间</th><th style="width:18%">责任部门</th><th style="width:16%">责任人</th></tr>
            <?php foreach ($rows('plan_items', 12) as $index => $row): ?>
            <tr><td class="center"><?= $index + 1 ?></td><td><?= $cell($row, 'check_object') ?></td><td><?= $cell($row, 'planned_time') ?></td><td><?= $cell($row, 'responsible_department') ?></td><td><?= $cell($row, 'responsible_person') ?></td></tr>
            <?php endforeach; ?>
        </table>
        <table style="margin-top:8px">
            <tr><th class="label">编制人（设备管理员）</th><td><?= $v('prepared_by') ?>　<?= $v('prepared_date') ?></td><th class="label">审核/批准人（技术负责人）</th><td><?= $v('approved_by') ?>　<?= $v('approved_date') ?></td></tr>
        </table>

    <?php elseif ($kind === 'period_scheme'): ?>
        <table>
            <tr><td colspan="4">根据仪器设备和标准物质期间核查年度计划，制定此方案，对编号为 <?= $v('checked_object') ?> 的设备或标准物质进行期间核查。</td></tr>
            <tr><th class="label">组长</th><td><?= $v('team_leader') ?></td><th class="label">组员</th><td><?= $text('team_members') ?></td></tr>
            <tr><th class="label">核查时间</th><td><?= $v('check_time') ?></td><th class="label">核查地点</th><td><?= $v('check_place') ?></td></tr>
            <tr><th class="label">执行文件</th><td colspan="3" class="tall"><?= $text('execution_files') ?></td></tr>
            <tr><th class="label">该仪器的检定周期时间或标准物质有效期</th><td colspan="3" class="tall"><?= $text('calibration_or_validity_period') ?></td></tr>
            <tr><th class="label">编制人/日期</th><td><?= $v('prepared_by') ?>　<?= $v('prepared_date') ?></td><th class="label">审核/批准人/日期</th><td><?= $v('approved_by') ?>　<?= $v('approved_date') ?></td></tr>
        </table>

    <?php elseif ($kind === 'period_record' || $kind === 'function_record'): ?>
        <table>
            <tr><th class="label">名称</th><td><?= $v('equipment_name') ?></td><th class="label">型号规格</th><td><?= $v('model_spec') ?></td></tr>
            <tr><th class="label">编号</th><td><?= $v('equipment_code') ?></td><th class="label">核查依据</th><td><?= $text('check_basis') ?></td></tr>
            <tr><th class="label">核查所用仪器设备或标准物质</th><td><?= $text('check_resources') ?></td><th class="label">核查人员</th><td><?= $text('check_personnel') ?></td></tr>
            <tr><th class="label">核查过程记录</th><td colspan="3" class="tall"><?= $text('process_record') ?><br><br>记录人（设备管理员）：<?= $v('recorder') ?>　日期：<?= $v('record_date') ?></td></tr>
            <tr>
                <th class="label"><?= $kind === 'function_record' ? '功能性核查结果' : '核查结果判定' ?></th>
                <td colspan="3" class="tall"><?= $kind === 'function_record' ? $text('function_result') : $text('result_judgement') ?><br><br>核查人员：<?= $v('checkers') ?>　日期：<?= $v('check_date') ?></td>
            </tr>
            <tr><th class="label">审核人意见</th><td colspan="3" class="tall"><?= $text('reviewer_opinion') ?><br><br>签名：<?= $v('reviewer') ?>　日期：<?= $v('review_date') ?></td></tr>
        </table>

    <?php elseif ($kind === 'period_report'): ?>
        <table>
            <tr><th class="label">名称</th><td><?= $v('equipment_name') ?></td><th class="label">型号规格</th><td><?= $v('model_spec') ?></td></tr>
            <tr><th class="label">编号</th><td><?= $v('equipment_code') ?></td><th class="label">核查依据</th><td><?= $text('check_basis') ?></td></tr>
            <tr><th class="label">核查项目</th><td><?= $text('check_items') ?></td><th class="label">核查人员</th><td><?= $text('check_personnel') ?></td></tr>
            <tr><th class="label">核查标准</th><td colspan="3" class="tall"><?= $text('check_standard') ?></td></tr>
            <tr><th class="label">核查结果判定</th><td colspan="3" class="tall"><?= $text('result_judgement') ?><br><br>负责人：<?= $v('responsible_person') ?>　日期：<?= $v('responsible_date') ?></td></tr>
            <tr><th class="label">期间核查评价</th><td colspan="3" class="tall"><?= $text('evaluation') ?><br><br>负责人：<?= $v('evaluation_responsible_person') ?>　日期：<?= $v('evaluation_date') ?></td></tr>
            <tr><th class="label">审核人意见</th><td colspan="3" class="tall"><?= $text('reviewer_opinion') ?><br><br>签名：<?= $v('reviewer') ?>　日期：<?= $v('review_date') ?></td></tr>
        </table>

    <?php else: ?>
        <table><tr><th class="label">模板</th><td><?= $templateName ?></td></tr></table>
    <?php endif; ?>

    <div class="footer">
        <span>系统重构打印模板</span>
        <span>生成日期：<?= date('Y-m-d') ?></span>
    </div>
</body>
</html>
