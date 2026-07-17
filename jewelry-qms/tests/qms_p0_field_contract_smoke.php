<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes = [];

function source(string $relativePath): string
{
    global $root;
    $path = $root . '/' . ltrim($relativePath, '/');
    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException('无法读取测试目标：' . $relativePath);
    }

    return $content;
}

function check(bool $condition, string $id, string $message): void
{
    global $failures, $passes;
    if ($condition) {
        $passes[] = $id . ' ' . $message;

        return;
    }

    $failures[] = $id . ' ' . $message;
}

function contains_all(string $haystack, array $needles): bool
{
    foreach ($needles as $needle) {
        if (!str_contains($haystack, $needle)) {
            return false;
        }
    }

    return true;
}

function contains_none(string $haystack, array $needles): bool
{
    foreach ($needles as $needle) {
        if (str_contains($haystack, $needle)) {
            return false;
        }
    }

    return true;
}

function template_has_names(string $template, array $fields): bool
{
    foreach ($fields as $field) {
        if (!preg_match('/\bname=["\']' . preg_quote($field, '/') . '["\']/', $template)) {
            return false;
        }
    }

    return true;
}

function template_has_no_names(string $template, array $fields): bool
{
    foreach ($fields as $field) {
        if (preg_match('/\bname=["\']' . preg_quote($field, '/') . '["\']/', $template)) {
            return false;
        }
    }

    return true;
}

$crud = source('app/controller/CrudBase.php');
$competencyController = source('app/controller/CompetencyRecord.php');
$nonconformityController = source('app/controller/Nonconformity.php');
$capaController = source('app/controller/Capa.php');
$complaintController = source('app/controller/Complaint.php');

$competencyTemplates = implode("\n", [
    source('app/view/competency_record/index.html'),
    source('app/view/competency_record/add.html'),
    source('app/view/competency_record/edit.html'),
    source('app/view/competency_record/view.html'),
]);
$nonconformityTemplates = implode("\n", [
    source('app/view/nonconformity/index.html'),
    source('app/view/nonconformity/add.html'),
    source('app/view/nonconformity/edit.html'),
    source('app/view/nonconformity/view.html'),
]);
$capaTemplates = implode("\n", [
    source('app/view/capa/index.html'),
    source('app/view/capa/add.html'),
    source('app/view/capa/edit.html'),
    source('app/view/capa/view.html'),
]);
$complaintTemplates = implode("\n", [
    source('app/view/complaint/index.html'),
    source('app/view/complaint/add.html'),
    source('app/view/complaint/edit.html'),
    source('app/view/complaint/view.html'),
]);

$competencyFields = [
    'employee_id',
    'test_item',
    'method_standard',
    'assessment_date',
    'assessor_id',
    'result',
    'authorization_scope',
    'valid_until',
];
$nonconformityFields = [
    'nc_number',
    'identified_date',
    'source',
    'severity',
    'disposition',
    'report_number',
    'assigned_to',
    'description',
    'impact_assessment',
    'immediate_action',
];
$capaEditFields = [
    'assigned_to',
    'due_date',
    'description',
    'root_cause',
    'corrective_action',
    'preventive_action',
    'verification',
];
$complaintFields = [
    'complaint_number',
    'customer_name',
    'contact',
    'received_date',
    'report_number',
    'assigned_to',
    'due_date',
    'description',
];
$genericWrongFields = ['name', 'code', 'department_id', 'responsible_person', 'remark'];

check(
    template_has_names($competencyTemplates, $competencyFields)
    && template_has_no_names($competencyTemplates, $genericWrongFields),
    'C01',
    '能力确认四页面只使用业务字段'
);
check(
    contains_all($competencyController, array_map(
        static fn (string $field): string => "'" . $field . "'",
        $competencyFields
    )) && str_contains($crud, 'onlyWritable('),
    'C02',
    '能力确认新增字段受服务端白名单约束'
);
check(
    str_contains($competencyTemplates, 'name="result"')
    && str_contains($competencyTemplates, 'name="valid_until"')
    && str_contains($crud, 'onlyWritable($this->request->post(), (string)$id)'),
    'C03',
    '能力确认编辑只保存提交的业务字段'
);
check(
    contains_all($competencyController, [
        "'employee_id' => 'require",
        "'test_item' => 'require",
        '请选择被评价人员',
        '检测项目不能为空',
    ]),
    'C04',
    '能力确认缺员工或项目时由服务端中文校验拒绝'
);

check(
    template_has_names($nonconformityTemplates, $nonconformityFields)
    && str_contains($nonconformityTemplates, 'qms_status_label'),
    'C05',
    '不符合新增、列表、详情使用同一业务字段和中文状态'
);
check(
    str_contains($nonconformityTemplates, 'name="impact_assessment"')
    && template_has_no_names($nonconformityTemplates, ['status'])
    && str_contains($crud, 'onlyWritable($this->request->post(), (string)$id)'),
    'C06',
    '不符合通用编辑不会覆盖工作流状态和未提交字段'
);
check(
    contains_all($nonconformityController, array_map(
        static fn (string $field): string => "'" . $field . "'",
        $nonconformityFields
    ))
    && contains_none($nonconformityController, ["'status' =>"])
    && str_contains($crud, 'array_intersect_key'),
    'C07',
    '不符合未知字段和伪造状态不落库'
);

check(
    contains_all($capaController, [
        'validationRules(array $data, ?string $recordId = null)',
        '$recordId === null',
        "\$rules['capa_number'] = 'require",
    ])
    && template_has_names($capaTemplates, $capaEditFields),
    'C08',
    'CAPA 编辑不要求重复提交编号且来源字段保持不变'
);
check(
    str_contains($capaController, "'description' => 'require")
    && str_contains($capaController, '问题描述不能为空'),
    'C09',
    'CAPA 空描述由服务端拒绝'
);

check(
    template_has_names($complaintTemplates, $complaintFields)
    && str_contains($complaintTemplates, 'qms_status_label'),
    'C10',
    '投诉新增、列表使用业务编号和中文状态'
);
check(
    contains_all($complaintController, array_map(
        static fn (string $field): string => "'" . $field . "'",
        $complaintFields
    ))
    && template_has_no_names($complaintTemplates, ['status', 'investigation', 'handling', 'response', 'closed_date']),
    'C11',
    '投诉通用编辑不会覆盖调查、处理、反馈和状态'
);
check(
    str_contains($crud, 'findActiveRecord(')
    && contains_all($crud, ["where('id', \$id)", "where('soft_delete', 0)"])
    && str_contains($complaintController, '$this->findActiveRecord((string)$id)'),
    'C12',
    '软删除投诉的旧详情、编辑和删除入口统一按不存在处理'
);

foreach ($passes as $pass) {
    echo "PASS {$pass}\n";
}
foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL {$failure}\n");
}

if ($failures !== []) {
    fwrite(STDERR, sprintf(
        "qms_p0_field_contract_smoke failed: %d passed, %d failed\n",
        count($passes),
        count($failures)
    ));
    exit(1);
}

echo "qms_p0_field_contract_smoke passed: C01-C12\n";
