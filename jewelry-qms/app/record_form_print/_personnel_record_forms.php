<?php
use app\service\RecordFormPrintService as P;

$kind = $personnelPrintTemplate ?? '';
$templateName = htmlspecialchars((string)($template['name'] ?? '人员记录表格'), ENT_QUOTES, 'UTF-8');
$docNumber = htmlspecialchars((string)($template['doc_number'] ?? ''), ENT_QUOTES, 'UTF-8');
$version = htmlspecialchars((string)($template['version'] ?? 'A/0'), ENT_QUOTES, 'UTF-8');
$pageSize = in_array($kind, ['certificate_registry', 'assessment_record'], true) ? 'A4 landscape' : 'A4';
$v = static fn (string $key, string $default = ''): string => P::value($values, $key, $default);
$text = static fn (string $key): string => nl2br(P::value($values, $key));
$cell = static fn (array $row, string $key): string => P::cell($row, $key);
$cellText = static fn (array $row, string $key): string => nl2br(P::cell($row, $key));
$rows = static function (string $key, int $minRows = 1) use ($values): array {
    $rows = P::rows($values, $key);
    while (count($rows) < $minRows) {
        $rows[] = [];
    }

    return $rows;
};
$checked = static fn (string $actual, string $expected): string => $actual === $expected ? '√' : '';
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title><?= $templateName ?></title>
    <style>
        @page { size: <?= $pageSize ?>; margin: 16mm 14mm; }
        body { font-family: "Noto Sans CJK SC", "Microsoft YaHei", Arial, sans-serif; color: #111; font-size: 12px; }
        .title { text-align: center; font-size: 20px; font-weight: 700; margin: 2px 0 12px; }
        .meta { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 11px; }
        .section-title { font-weight: 700; margin: 10px 0 5px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #111; padding: 6px 7px; vertical-align: middle; word-break: break-word; }
        th { background: #f4f4f4; font-weight: 700; text-align: center; }
        .center { text-align: center; }
        .small { font-size: 10.5px; }
        .label { width: 15%; background: #f4f4f4; font-weight: 700; text-align: center; }
        .tall { height: 68px; }
        .signature { height: 42px; }
        .footer { display: flex; justify-content: space-between; margin-top: 9px; font-size: 10.5px; }
        <?= P::tablePaginationCss() ?>
    </style>
</head>
<body>
    <div class="title"><?php if ($kind === 'annual_training_plan'): ?><?= $v('plan_year', date('Y')) ?>年度人员培训计划表<?php else: ?><?= $templateName ?><?php endif; ?></div>
    <div class="meta">
        <span>编号：<?= $docNumber ?></span>
        <span>版本：<?= $version ?></span>
    </div>

    <?php if ($kind === 'annual_training_plan'): ?>
        <table>
            <thead>
            <tr>
                <th style="width:7%">序号</th>
                <th style="width:16%">培训时间</th>
                <th>培训内容</th>
                <th style="width:18%">培训对象</th>
                <th style="width:18%">培训部门</th>
                <th style="width:16%">备注</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows('training_plan_items', 8) as $index => $row): ?>
            <tr>
                <td class="center"><?= $index + 1 ?></td>
                <td><?= $cell($row, 'training_time') ?></td>
                <td><?= $cellText($row, 'training_content') ?></td>
                <td><?= $cell($row, 'training_target') ?></td>
                <td><?= $cell($row, 'training_department') ?></td>
                <td><?= $cell($row, 'remarks') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

    <?php elseif ($kind === 'certificate_registry'): ?>
        <table class="small">
            <thead>
            <tr>
                <th style="width:5%">序号</th>
                <th style="width:9%">姓名</th>
                <th style="width:11%">证别</th>
                <th style="width:16%">证书号码</th>
                <th style="width:12%">初次取证时间</th>
                <th style="width:15%">发证单位</th>
                <th style="width:11%">有效期</th>
                <th style="width:12%">上次复审时间</th>
                <th>备注</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows('certificate_items', 10) as $index => $row): ?>
            <tr>
                <td class="center"><?= $index + 1 ?></td>
                <td><?= $cell($row, 'name') ?></td>
                <td><?= $cell($row, 'certificate_type') ?></td>
                <td><?= $cell($row, 'certificate_number') ?></td>
                <td><?= $cell($row, 'first_issued_date') ?></td>
                <td><?= $cell($row, 'issuer') ?></td>
                <td><?= $cell($row, 'valid_until') ?></td>
                <td><?= $cell($row, 'last_review_date') ?></td>
                <td><?= $cell($row, 'remarks') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

    <?php elseif ($kind === 'assessment_record'): ?>
        <table class="small">
            <thead>
            <tr>
                <th rowspan="2" style="width:5%">序号</th>
                <th rowspan="2" style="width:9%">姓名</th>
                <th rowspan="2">考核项目</th>
                <th colspan="3">考核方式</th>
                <th rowspan="2" style="width:14%">主持考核部门</th>
                <th rowspan="2" style="width:11%">考核时间</th>
            </tr>
            <tr>
                <th style="width:10%">提问是否合格</th>
                <th style="width:12%">实际操作是否合格</th>
                <th style="width:9%">笔试成绩</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows('assessment_items', 8) as $index => $row): ?>
            <tr>
                <td class="center"><?= $index + 1 ?></td>
                <td><?= $cell($row, 'name') ?></td>
                <td><?= $cellText($row, 'assessment_project') ?></td>
                <td><?= $cell($row, 'oral_result') ?></td>
                <td><?= $cell($row, 'operation_result') ?></td>
                <td class="center"><?= $cell($row, 'written_score') ?></td>
                <td><?= $cell($row, 'host_department') ?></td>
                <td><?= $cell($row, 'assessment_date') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

    <?php elseif ($kind === 'prejob_assessment'): ?>
        <table>
            <tr>
                <th class="label">姓名</th><td><?= $v('trainee_name') ?></td>
                <th class="label">性别</th><td><?= $v('gender') ?></td>
                <th class="label">出生年月</th><td><?= $v('birth_month') ?></td>
            </tr>
            <tr>
                <th class="label">参加工作时间</th><td><?= $v('work_start_date') ?></td>
                <th class="label">文化程度</th><td><?= $v('education_level') ?></td>
                <th class="label">所学专业</th><td><?= $v('major') ?></td>
            </tr>
            <tr>
                <th class="label">现岗位名称</th><td colspan="5"><?= $v('current_position') ?></td>
            </tr>
        </table>
        <table style="margin-top:8px">
            <thead>
            <tr>
                <th>培训内容</th>
                <th style="width:18%">完成情况</th>
                <th style="width:18%">考核成绩</th>
                <th style="width:18%">备注</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows('prejob_training_items', 5) as $row): ?>
            <tr>
                <td><?= $cellText($row, 'training_content') ?></td>
                <td><?= $cell($row, 'completion_status') ?></td>
                <td><?= $cell($row, 'assessment_score') ?></td>
                <td><?= $cell($row, 'remarks') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <table style="margin-top:8px">
            <tr>
                <th class="label">技术负责人意见</th>
                <td class="tall"><?= $text('technical_manager_opinion') ?></td>
                <th style="width:12%">日期</th>
                <td style="width:20%"><?= $v('technical_manager_date') ?></td>
            </tr>
            <tr>
                <th class="label">实验室主任考核意见</th>
                <td class="tall"><?= $text('lab_director_opinion') ?></td>
                <th>日期</th>
                <td><?= $v('lab_director_date') ?></td>
            </tr>
            <tr>
                <th class="label">备注</th>
                <td colspan="3"><?= $text('remarks') ?></td>
            </tr>
        </table>

    <?php elseif ($kind === 'training_application'): ?>
        <table>
            <tr>
                <th class="label">申请培训内容</th>
                <td colspan="3" class="tall"><?= $text('training_content') ?></td>
            </tr>
            <tr>
                <th class="label">培训单位</th><td><?= $v('training_provider') ?></td>
                <th class="label">培训地点</th><td><?= $v('training_place') ?></td>
            </tr>
            <tr>
                <th class="label">培训时间</th><td><?= $v('training_time') ?></td>
                <th class="label">所需费用</th><td><?= $v('estimated_cost') ?></td>
            </tr>
        </table>
        <div class="section-title">参加人员</div>
        <table>
            <tr><th style="width:10%">序号</th><th>姓名</th><th>部门</th><th>签名</th></tr>
            <?php foreach ($rows('participants', 5) as $index => $row): ?>
            <tr>
                <td class="center"><?= $index + 1 ?></td>
                <td><?= $cell($row, 'name') ?></td>
                <td><?= $cell($row, 'department') ?></td>
                <td class="signature"><?= $cell($row, 'signature') ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <table style="margin-top:8px">
            <tr>
                <th class="label">申请培训部门</th><td><?= $v('application_department') ?></td>
                <th class="label">负责人</th><td><?= $v('application_responsible_person') ?></td>
                <th class="label">日期</th><td><?= $v('application_date') ?></td>
            </tr>
            <tr>
                <th class="label">申请部门意见</th>
                <td colspan="5" class="tall"><?= $text('application_opinion') ?></td>
            </tr>
            <tr>
                <th class="label">审核</th>
                <td colspan="3" class="tall"><?= $text('audit_opinion') ?></td>
                <th class="label">审核人/日期</th>
                <td><?= $v('auditor') ?><br><?= $v('audit_date') ?></td>
            </tr>
            <tr>
                <th class="label">批准</th>
                <td colspan="3" class="tall"><?= $text('approval_opinion') ?></td>
                <th class="label">批准人/日期</th>
                <td><?= $v('approver') ?><br><?= $v('approval_date') ?></td>
            </tr>
            <tr>
                <th class="label">备注</th>
                <td colspan="5"><?= $text('remarks') ?></td>
            </tr>
        </table>

    <?php elseif ($kind === 'personnel_profile'): ?>
        <table class="small">
            <tr>
                <th class="label">入职时间</th><td><?= $v('hire_date') ?></td>
                <th class="label">所属部门</th><td><?= $v('department') ?></td>
                <th class="label">应聘职位</th><td><?= $v('applied_position') ?></td>
            </tr>
            <tr>
                <th class="label">姓名</th><td><?= $v('employee_name') ?></td>
                <th class="label">性别</th><td><?= $v('gender') ?></td>
                <th class="label">民族</th><td><?= $v('ethnicity') ?></td>
            </tr>
            <tr>
                <th class="label">出生年月</th><td><?= $v('birth_month') ?></td>
                <th class="label">身高</th><td><?= $v('height') ?></td>
                <th class="label">籍贯</th><td><?= $v('native_place') ?></td>
            </tr>
            <tr>
                <th class="label">毕业院校</th><td><?= $v('graduate_school') ?></td>
                <th class="label">专业</th><td><?= $v('major') ?></td>
                <th class="label">学历</th><td><?= $v('education_level') ?></td>
            </tr>
            <tr>
                <th class="label">毕业时间</th><td><?= $v('graduation_date') ?></td>
                <th class="label">政治面貌</th><td><?= $v('political_status') ?></td>
                <th class="label">参加工作时间</th><td><?= $v('work_start_date') ?></td>
            </tr>
            <tr>
                <th class="label">户口所在地</th><td><?= $v('registered_address') ?></td>
                <th class="label">婚姻状况</th><td><?= $v('marital_status') ?></td>
                <th class="label">身份证号码</th><td><?= $v('id_number') ?></td>
            </tr>
            <tr>
                <th class="label">详细住址</th><td colspan="3"><?= $text('address') ?></td>
                <th class="label">电子邮箱</th><td><?= $v('email') ?></td>
            </tr>
            <tr>
                <th class="label">联系方式</th><td><?= $v('phone') ?></td>
                <th class="label">备用联系方式</th><td><?= $v('backup_phone') ?></td>
                <th class="label">专业技术资格证书</th><td><?= $v('qualification_certificate') ?></td>
            </tr>
        </table>
        <div class="section-title">教育及培训经历</div>
        <table class="small">
            <tr><th style="width:20%">起止年月</th><th>学校名称</th><th>所学专业</th></tr>
            <?php foreach ($rows('education_items', 3) as $row): ?>
            <tr><td><?= $cell($row, 'period') ?></td><td><?= $cell($row, 'school') ?></td><td><?= $cell($row, 'major') ?></td></tr>
            <?php endforeach; ?>
        </table>
        <div class="section-title">工作经历</div>
        <table class="small">
            <tr><th style="width:18%">起止年月</th><th>工作单位</th><th style="width:16%">岗位</th><th style="width:14%">何时离职</th><th style="width:22%">证明人及电话</th></tr>
            <?php foreach ($rows('work_items', 3) as $row): ?>
            <tr><td><?= $cell($row, 'period') ?></td><td><?= $cell($row, 'company') ?></td><td><?= $cell($row, 'position') ?></td><td><?= $cell($row, 'leave_time') ?></td><td><?= $cell($row, 'witness') ?></td></tr>
            <?php endforeach; ?>
        </table>
        <div class="section-title">家庭成员</div>
        <table class="small">
            <tr><th style="width:12%">称谓</th><th style="width:16%">姓名</th><th style="width:18%">出生年月</th><th>工作单位及职务</th><th style="width:20%">联系电话</th></tr>
            <?php foreach ($rows('family_items', 3) as $row): ?>
            <tr><td><?= $cell($row, 'relationship') ?></td><td><?= $cell($row, 'name') ?></td><td><?= $cell($row, 'birth_month') ?></td><td><?= $cell($row, 'work_unit') ?></td><td><?= $cell($row, 'phone') ?></td></tr>
            <?php endforeach; ?>
        </table>
        <table style="margin-top:8px">
            <tr><th class="label">自我评价</th><td class="tall"><?= $text('self_evaluation') ?></td></tr>
            <tr><th class="label">承诺签名/日期</th><td><?= $v('commitment_signature') ?>　<?= $v('commitment_date') ?></td></tr>
        </table>

    <?php elseif ($kind === 'capability_confirmation'): ?>
        <table>
            <tr>
                <th class="label">姓名</th><td><?= $v('employee_name') ?></td>
                <th class="label">部门/岗位</th><td><?= $v('department_position') ?></td>
                <th class="label">入职时间</th><td><?= $v('hire_date') ?></td>
            </tr>
            <tr>
                <th class="label">工作年限</th><td><?= $v('work_years') ?></td>
                <th class="label">职称</th><td><?= $v('title') ?></td>
                <th class="label">学历</th><td><?= $v('education_level') ?></td>
            </tr>
            <tr>
                <th class="label">毕业院校</th><td><?= $v('graduate_school') ?></td>
                <th class="label">专业</th><td colspan="3"><?= $v('major') ?></td>
            </tr>
            <tr>
                <th class="label">工作经历和培训情况概述</th>
                <td colspan="5" class="tall"><?= $text('experience_summary') ?></td>
            </tr>
        </table>
        <table style="margin-top:8px" class="small">
            <thead>
            <tr>
                <th rowspan="2" style="width:6%">序号</th>
                <th rowspan="2">能力确认情况/内容</th>
                <th rowspan="2" style="width:16%">确认方式</th>
                <th colspan="4" style="width:28%">结果</th>
            </tr>
            <tr>
                <th>不合格</th><th>合格</th><th>良好</th><th>优秀</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows('capability_items', 7) as $index => $row): ?>
            <?php $result = htmlspecialchars_decode($cell($row, 'result'), ENT_QUOTES); ?>
            <tr>
                <td class="center"><?= $index + 1 ?></td>
                <td><?= $cellText($row, 'content') ?></td>
                <td><?= $cell($row, 'confirmation_method') ?></td>
                <td class="center"><?= $checked($result, '不合格') ?></td>
                <td class="center"><?= $checked($result, '合格') ?></td>
                <td class="center"><?= $checked($result, '良好') ?></td>
                <td class="center"><?= $checked($result, '优秀') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <table style="margin-top:8px">
            <tr>
                <th class="label">综合确认结果</th>
                <td class="tall"><?= $text('confirmation_result') ?></td>
                <th style="width:12%">确认人/日期</th>
                <td style="width:24%"><?= $v('confirmer') ?><br><?= $v('confirmation_date') ?></td>
            </tr>
            <tr>
                <th class="label">授权结果</th>
                <td class="tall"><?= $text('authorization_result') ?></td>
                <th>授权人/日期</th>
                <td><?= $v('authorizer') ?><br><?= $v('authorization_date') ?></td>
            </tr>
        </table>

    <?php elseif ($kind === 'training_evaluation'): ?>
        <table>
            <tr>
                <th class="label">姓名</th><td><?= $v('employee_name') ?></td>
                <th class="label">所在部门</th><td><?= $v('department') ?></td>
                <th class="label">岗位</th><td><?= $v('position') ?></td>
            </tr>
            <tr>
                <th class="label">培训性质</th><td><?= $v('training_nature') ?></td>
                <th class="label">培训方式</th><td><?= $v('training_method') ?></td>
                <th class="label">培训时间</th><td><?= $v('training_time') ?></td>
            </tr>
            <tr>
                <th class="label">培训单位/讲师</th><td colspan="3"><?= $v('training_provider_or_trainer') ?></td>
                <th class="label">证书编号</th><td><?= $v('certificate_number') ?></td>
            </tr>
            <tr><th class="label">培训的主要内容</th><td colspan="5" class="tall"><?= $text('training_main_content') ?></td></tr>
            <tr><th class="label">考核评价方式</th><td colspan="5" class="tall"><?= $text('assessment_method') ?></td></tr>
            <tr><th class="label">考核内容</th><td colspan="5" class="tall"><?= $text('assessment_content') ?></td></tr>
            <tr><th class="label">考核结果</th><td colspan="5" class="tall"><?= $text('assessment_result') ?></td></tr>
            <tr>
                <th class="label">监督员/日期</th>
                <td colspan="2"><?= $v('supervisor') ?>　<?= $v('supervisor_date') ?></td>
                <th class="label">负责人/日期</th>
                <td colspan="2"><?= $v('responsible_person') ?>　<?= $v('responsible_date') ?></td>
            </tr>
            <tr><th class="label">评价意见</th><td colspan="5" class="tall"><?= $text('evaluation_opinion') ?></td></tr>
            <tr><th class="label">备注</th><td colspan="5"><?= $text('remarks') ?></td></tr>
        </table>

    <?php else: ?>
        <table>
            <tr><th class="label">模板</th><td><?= $templateName ?></td></tr>
            <tr><th class="label">说明</th><td>人员记录表格打印模板未匹配到具体类型。</td></tr>
        </table>
    <?php endif; ?>

    <div class="footer">
        <span>系统重构打印模板</span>
        <span>生成日期：<?= date('Y-m-d') ?></span>
    </div>
</body>
</html>
