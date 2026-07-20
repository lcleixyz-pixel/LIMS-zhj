<?php
use app\service\RecordFormPrintService as P;

$kind = $fileControlPrintTemplate ?? '';
$templateName = htmlspecialchars((string)($template['name'] ?? '文件控制记录表格'), ENT_QUOTES, 'UTF-8');
$docNumber = htmlspecialchars((string)($template['doc_number'] ?? ''), ENT_QUOTES, 'UTF-8');
$version = htmlspecialchars((string)($template['version'] ?? 'A/0'), ENT_QUOTES, 'UTF-8');
$wideKinds = ['controlled_file_register', 'external_file_register', 'distribution_recovery', 'borrow_register', 'sample_original_record'];
$pageSize = in_array($kind, $wideKinds, true) ? 'A4 landscape' : 'A4';
$v = static fn (string $key, string $default = ''): string => P::value($values, $key, $default);
$text = static fn (string $key, string $default = ''): string => nl2br(P::value($values, $key, $default));
$cell = static fn (array $row, string $key): string => P::cell($row, $key);
$cellText = static fn (array $row, string $key): string => nl2br(P::cell($row, $key));
$rows = static function (string $key, int $minRows = 1) use ($values): array {
    $rows = P::rows($values, $key);
    while (count($rows) < $minRows) {
        $rows[] = [];
    }

    return $rows;
};
$check = static fn (string $key): string => trim((string)($values[$key] ?? '')) === '1' ? '☑' : '□';
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title><?= $templateName ?></title>
    <style>
        @page { size: <?= $pageSize ?>; margin: 15mm 13mm; }
        body { font-family: "Noto Sans CJK SC", "Microsoft YaHei", Arial, sans-serif; color: #111; font-size: 12px; }
        .title { text-align: center; font-size: 20px; font-weight: 700; margin: 2px 0 11px; letter-spacing: 0; }
        .meta { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 11px; }
        .section-title { font-weight: 700; margin: 10px 0 5px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #111; padding: 6px 7px; vertical-align: middle; word-break: break-word; }
        th { background: #f4f4f4; font-weight: 700; text-align: center; }
        .label { width: 17%; background: #f4f4f4; font-weight: 700; text-align: center; }
        .center { text-align: center; }
        .small { font-size: 10.5px; }
        .tiny { font-size: 9.7px; }
        .sample-table th, .sample-table td { padding: 5px 4px; font-size: 9.3px; }
        .tall { height: 72px; }
        .extra-tall { height: 100px; }
        .sign-row { height: 42px; }
        .no-border { border: 0; }
        .footer { display: flex; justify-content: space-between; margin-top: 9px; font-size: 10.5px; }
        <?= P::tablePaginationCss() ?>
    </style>
</head>
<body>
    <div class="title"><?= $templateName ?></div>
    <div class="meta">
        <span>记录编号：<?= $docNumber ?></span>
        <span>版本：<?= $version ?></span>
    </div>

    <?php if ($kind === 'controlled_file_register'): ?>
        <table class="small">
            <thead>
            <tr>
                <th style="width:5%">序号</th>
                <th style="width:18%">文件名称</th>
                <th style="width:15%">文件控制编号</th>
                <th style="width:10%">版本号</th>
                <th>编制人</th>
                <th>审核人</th>
                <th>批准人</th>
                <th style="width:12%">批准日期</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows('controlled_file_items', 10) as $index => $row): ?>
            <tr>
                <td class="center"><?= $index + 1 ?></td>
                <td><?= $cell($row, 'document_name') ?></td>
                <td><?= $cell($row, 'document_code') ?></td>
                <td><?= $cell($row, 'version') ?></td>
                <td><?= $cell($row, 'prepared_by') ?></td>
                <td><?= $cell($row, 'reviewed_by') ?></td>
                <td><?= $cell($row, 'approved_by') ?></td>
                <td><?= $cell($row, 'approval_date') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="section-title small">填表说明：质量记录和技术记录虽然也属于文件，但在记录控制程序中进行控制；文件控制编号也就是文件代号；此表由资料员填写。</div>

    <?php elseif ($kind === 'external_file_register'): ?>
        <table>
            <thead>
            <tr>
                <th style="width:6%">序号</th>
                <th style="width:18%">内部控制编号</th>
                <th>文件名称</th>
                <th style="width:18%">文件原编号</th>
                <th style="width:9%">数量</th>
                <th style="width:20%">备注</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows('external_file_items', 12) as $index => $row): ?>
            <tr>
                <td class="center"><?= $index + 1 ?></td>
                <td><?= $cell($row, 'internal_control_number') ?></td>
                <td><?= $cell($row, 'document_name') ?></td>
                <td><?= $cell($row, 'original_number') ?></td>
                <td><?= $cell($row, 'quantity') ?></td>
                <td><?= $cell($row, 'remarks') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="section-title small">填表说明：技术标准另由《现行有效标准清单》控制；此表由资料员填写。</div>

    <?php elseif ($kind === 'distribution_recovery'): ?>
        <table class="tiny">
            <thead>
            <tr>
                <th style="width:4%">序号</th>
                <th style="width:15%">文件名称</th>
                <th style="width:12%">文件控制编号</th>
                <th style="width:7%">版本</th>
                <th style="width:12%">发放编号</th>
                <th>发放人</th>
                <th>签收人</th>
                <th>签收部门</th>
                <th>发放日期</th>
                <th>交回人</th>
                <th>签收人</th>
                <th>回收日期</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows('distribution_items', 12) as $index => $row): ?>
            <tr>
                <td class="center"><?= $index + 1 ?></td>
                <td><?= $cellText($row, 'document_name') ?></td>
                <td><?= $cell($row, 'document_code') ?></td>
                <td><?= $cell($row, 'version') ?></td>
                <td><?= $cell($row, 'distribution_number') ?></td>
                <td><?= $cell($row, 'issuer') ?></td>
                <td><?= $cell($row, 'recipient') ?></td>
                <td><?= $cell($row, 'recipient_department') ?></td>
                <td><?= $cell($row, 'issue_date') ?></td>
                <td><?= $cell($row, 'returned_by') ?></td>
                <td><?= $cell($row, 'return_receiver') ?></td>
                <td><?= $cell($row, 'return_date') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="section-title small">填表说明：此表由资料员填写，发放人、签收人、发放日期、交回人、签收人、回收日期应手写确认。</div>

    <?php elseif ($kind === 'borrow_register'): ?>
        <table>
            <thead>
            <tr>
                <th style="width:6%">序号</th>
                <th>文件名称</th>
                <th style="width:17%">文件控制编号</th>
                <th style="width:13%">借阅人</th>
                <th style="width:13%">发放人</th>
                <th style="width:13%">借阅日期</th>
                <th style="width:13%">归还日期</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows('borrow_items', 12) as $index => $row): ?>
            <tr>
                <td class="center"><?= $index + 1 ?></td>
                <td><?= $cell($row, 'document_name') ?></td>
                <td><?= $cell($row, 'document_code') ?></td>
                <td><?= $cell($row, 'borrower') ?></td>
                <td><?= $cell($row, 'issuer') ?></td>
                <td><?= $cell($row, 'borrow_date') ?></td>
                <td><?= $cell($row, 'return_date') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="section-title small">填表说明：借阅人、发放人、借阅日期、归还日期应手写确认。</div>

    <?php elseif ($kind === 'replacement_request'): ?>
        <table>
            <tr><th class="label">文件名称</th><td><?= $v('document_name') ?></td><th class="label">文件控制编号</th><td><?= $v('document_code') ?></td></tr>
            <tr><th class="label">发放编号</th><td><?= $v('distribution_number') ?></td><th class="label">申请人</th><td><?= $v('applicant') ?></td></tr>
            <tr><th class="label">数量</th><td><?= $v('quantity') ?></td><th class="label">申请日期</th><td><?= $v('application_date') ?></td></tr>
            <tr><th class="label">申请理由</th><td colspan="3" class="extra-tall"><?= $text('application_reason') ?><br><br>申请人：<?= $v('applicant') ?>　日期：<?= $v('application_date') ?></td></tr>
            <tr><th class="label">批准意见</th><td colspan="3" class="extra-tall"><?= $text('approval_opinion') ?><br><br>批准人（质量负责人）：<?= $v('quality_manager') ?>　日期：<?= $v('approval_date') ?></td></tr>
        </table>
        <div class="section-title small">填表说明：此表由申请更换或补领的人员填写。</div>

    <?php elseif ($kind === 'change_approval'): ?>
        <table>
            <tr><th class="label">文件名称</th><td><?= $v('document_name') ?></td><th class="label">文件控制编号</th><td><?= $v('document_code') ?></td></tr>
            <tr><th class="label">申请人</th><td><?= $v('applicant') ?></td><th class="label">提出日期</th><td><?= $v('proposed_date') ?></td></tr>
            <tr>
                <th class="label">更改理由</th>
                <td colspan="3">
                    <?= $check('reason_customer_need') ?> 客户需求　
                    <?= $check('reason_law_requirement') ?> 法律法规要求　
                    <?= $check('reason_external_audit') ?> 外部审核提出　
                    <?= $check('reason_management_review') ?> 管理评审提出　
                    <?= $check('reason_system_improvement') ?> 完善体系文件
                </td>
            </tr>
            <tr><th class="label">修改前内容</th><td colspan="3" class="extra-tall"><?= $text('before_content') ?></td></tr>
            <tr><th class="label">修改后内容</th><td colspan="3" class="extra-tall"><?= $text('after_content') ?></td></tr>
            <tr><th class="label">审核意见</th><td colspan="3" class="tall"><?= $text('review_opinion') ?><br><br>审核人（签字）：<?= $v('reviewer') ?>　日期：<?= $v('review_date') ?></td></tr>
            <tr><th class="label">批准意见</th><td colspan="3" class="tall"><?= $text('approval_opinion') ?><br><br>批准人（签字）：<?= $v('approver') ?>　日期：<?= $v('approval_date') ?></td></tr>
        </table>
        <div class="section-title small">填表说明：审核人和批准人原则上为文件的原审核人和批准人；如更换，应具备同等职务。</div>

    <?php elseif ($kind === 'destruction_record'): ?>
        <table>
            <tr><th class="label">文件名称</th><td colspan="3"><?= $v('document_name') ?></td></tr>
            <tr><th class="label">发放编号</th><td colspan="3"><?= $v('distribution_number') ?></td></tr>
            <tr><th class="label">文件销毁原因</th><td colspan="3" class="tall"><?= $text('destruction_reason') ?><br><br>申请人（资料管理员）：<?= $v('applicant') ?>　日期：<?= $v('application_date') ?></td></tr>
            <tr><th class="label">审批意见</th><td colspan="3" class="tall"><?= $text('approval_opinion') ?><br><br>批准人（质量负责人）：<?= $v('approver') ?>　日期：<?= $v('approval_date') ?></td></tr>
            <tr><th class="label">销毁日期</th><td><?= $v('destroy_date') ?></td><th class="label">销毁人</th><td><?= $v('destroyer') ?></td></tr>
            <tr><th class="label">销毁文件份数</th><td><?= $v('copy_count') ?></td><th class="label">监销人</th><td><?= $v('supervisor') ?></td></tr>
        </table>
        <div class="section-title small">填表说明：此表由资料员填写。</div>

    <?php elseif ($kind === 'meeting_sign_in'): ?>
        <table>
            <tr><th class="label">会议主题</th><td><?= $v('meeting_topic') ?></td><th class="label">时间</th><td><?= $v('meeting_time') ?></td></tr>
            <tr><th class="label">地点</th><td colspan="3"><?= $v('meeting_place') ?></td></tr>
        </table>
        <div class="section-title">参会签到</div>
        <table>
            <tr><th style="width:8%">序号</th><th>姓名</th><th>部门</th><th>签名</th></tr>
            <?php foreach ($rows('attendees', 8) as $index => $row): ?>
            <tr><td class="center"><?= $index + 1 ?></td><td><?= $cell($row, 'name') ?></td><td><?= $cell($row, 'department') ?></td><td><?= $cell($row, 'signature') ?></td></tr>
            <?php endforeach; ?>
        </table>
        <table style="margin-top:8px">
            <tr><th class="label">会议内容</th><td class="extra-tall"><?= $text('meeting_content') ?></td></tr>
            <tr><th class="label">记录人</th><td><?= $v('recorder') ?></td></tr>
        </table>

    <?php elseif ($kind === 'sample_original_record'): ?>
        <table class="sample-table">
            <colgroup>
                <col style="width:7%">
                <col style="width:10%">
                <col style="width:7%">
                <col style="width:8%">
                <col style="width:10%">
                <col style="width:11%">
                <col style="width:7%">
                <col style="width:8%">
                <col style="width:8%">
                <col style="width:9%">
                <col style="width:15%">
            </colgroup>
            <tr>
                <th>日期</th>
                <th>样品编号</th>
                <th>总质量（g）</th>
                <th>密度（g/cm³）</th>
                <th>折射率/双折射率</th>
                <th>放大检查</th>
                <th>多色性</th>
                <th>光性特征</th>
                <th>紫外荧光</th>
                <th>吸收光谱</th>
                <th>检测结论</th>
            </tr>
            <tr class="extra-tall">
                <td><?= $v('test_date') ?></td>
                <td><?= $v('sample_number') ?></td>
                <td><?= $v('total_mass') ?></td>
                <td><?= $v('density') ?></td>
                <td><?= $v('refractive_index') ?></td>
                <td><?= $text('magnification') ?></td>
                <td><?= $v('pleochroism') ?></td>
                <td><?= $v('optical_character') ?></td>
                <td><?= $v('uv_fluorescence') ?></td>
                <td><?= $text('absorption_spectrum') ?></td>
                <td><?= $text('test_conclusion') ?></td>
            </tr>
        </table>
        <table style="margin-top:8px">
            <tr><th class="label">检测员</th><td><?= $v('tester') ?></td><th class="label">记录员</th><td><?= $v('recorder') ?></td><th class="label">校核员</th><td><?= $v('verifier') ?></td></tr>
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
