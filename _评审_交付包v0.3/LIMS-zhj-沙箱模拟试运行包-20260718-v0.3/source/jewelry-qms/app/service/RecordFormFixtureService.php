<?php
declare(strict_types=1);

namespace app\service;

use app\model\RecordFormTemplate;

class RecordFormFixtureService
{
    public static function seed(): int
    {
        $count = 0;
        foreach (self::templates() as $row) {
            $existing = RecordFormTemplate::where('doc_number', $row['doc_number'])
                ->where('soft_delete', 0)
                ->find();
            $row['field_schema'] = RecordFormSchemaService::encode($row['field_schema']);

            if ($existing) {
                $existing->save($row);
            } else {
                $row['id'] = qms_uuid();
                RecordFormTemplate::create($row);
            }

            $count++;
        }

        return $count;
    }

    public static function templates(): array
    {
        return [
            self::template('XZTC/BG-01-01', '年度人员培训计划表', 'rf_xztc_bg_01_01_5325a1b0bd', self::annualTrainingPlanSchema()),
            self::template('XZTC/BG-01-02', '人员培训记录表', 'training_record', self::trainingRecordSchema()),
            self::template('XZTC/BG-01-03', '检测人员持证登记表', 'rf_xztc_bg_01_03_5fa5a364df', self::certificateRegistrySchema()),
            self::template('XZTC/BG-01-04', '人员考核记录表', 'rf_xztc_bg_01_04_5fb52565ba', self::assessmentRecordSchema()),
            self::template('XZTC/BG-01-05', '岗前培训考核记录表', 'rf_xztc_bg_01_05_66b005b382', self::prejobAssessmentSchema()),
            self::template('XZTC/BG-01-06', '培训申请表', 'rf_xztc_bg_01_06_f268e9aaf1', self::trainingApplicationSchema()),
            self::template('XZTC/BG-01-07', '人员档案登记表', 'rf_xztc_bg_01_07_a0956d356f', self::personnelProfileSchema()),
            self::template('XZTC/BG-01-08', '人员能力确认表', 'rf_xztc_bg_01_08_6fcb518418', self::capabilityConfirmationSchema()),
            self::template('XZTC/BG-01-09', '人员培训评价表', 'rf_xztc_bg_01_09_5f54bbf750', self::trainingEvaluationSchema()),
        ];
    }

    private static function template(string $docNumber, string $name, string $printTemplateKey, array $schema): array
    {
        return [
            'doc_number' => $docNumber,
            'name' => $name,
            'module' => '人员培训程序',
            'print_template_key' => $printTemplateKey,
            'version' => 'A/0',
            'status' => 'published',
            'review_status' => 'completed',
            'review_note' => '已按高保真打印模板完成，可正式填写。',
            'field_schema' => $schema,
        ];
    }

    private static function annualTrainingPlanSchema(): array
    {
        return [
            ['key' => 'plan_year', 'label' => '计划年度', 'type' => 'text', 'required' => true],
            ['key' => 'training_plan_items', 'label' => '培训计划明细', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'training_time', 'label' => '培训时间', 'type' => 'text', 'required' => true],
                ['key' => 'training_content', 'label' => '培训内容', 'type' => 'textarea', 'required' => true],
                ['key' => 'training_target', 'label' => '培训对象', 'type' => 'text', 'required' => false],
                ['key' => 'training_department', 'label' => '培训部门', 'type' => 'department', 'required' => false],
                ['key' => 'remarks', 'label' => '备注', 'type' => 'text', 'required' => false],
            ]],
        ];
    }

    private static function trainingRecordSchema(): array
    {
        return [
            ['key' => 'training_date', 'label' => '培训日期', 'type' => 'date', 'required' => true],
            ['key' => 'training_topic', 'label' => '培训主题', 'type' => 'text', 'required' => true],
            ['key' => 'trainer', 'label' => '培训讲师', 'type' => 'text', 'required' => true],
            ['key' => 'training_content', 'label' => '培训内容', 'type' => 'textarea', 'required' => false],
            ['key' => 'attendees', 'label' => '参训人员', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'name', 'label' => '姓名', 'type' => 'person', 'required' => true],
                ['key' => 'department', 'label' => '部门', 'type' => 'department', 'required' => false],
                ['key' => 'signature', 'label' => '签名', 'type' => 'signature', 'required' => false],
            ]],
            ['key' => 'effect_evaluation', 'label' => '效果评价', 'type' => 'textarea', 'required' => false],
        ];
    }

    private static function certificateRegistrySchema(): array
    {
        return [
            ['key' => 'certificate_items', 'label' => '持证登记明细', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'name', 'label' => '姓名', 'type' => 'person', 'required' => true],
                ['key' => 'certificate_type', 'label' => '证别', 'type' => 'text', 'required' => false],
                ['key' => 'certificate_number', 'label' => '证书号码', 'type' => 'text', 'required' => false],
                ['key' => 'first_issued_date', 'label' => '初次取证时间', 'type' => 'date', 'required' => false],
                ['key' => 'issuer', 'label' => '发证单位', 'type' => 'text', 'required' => false],
                ['key' => 'valid_until', 'label' => '有效期', 'type' => 'date', 'required' => false],
                ['key' => 'last_review_date', 'label' => '上次复审时间', 'type' => 'date', 'required' => false],
                ['key' => 'remarks', 'label' => '备注', 'type' => 'text', 'required' => false],
            ]],
        ];
    }

    private static function assessmentRecordSchema(): array
    {
        return [
            ['key' => 'assessment_items', 'label' => '人员考核明细', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'name', 'label' => '姓名', 'type' => 'person', 'required' => true],
                ['key' => 'assessment_project', 'label' => '考核项目', 'type' => 'textarea', 'required' => true],
                ['key' => 'oral_result', 'label' => '提问是否合格', 'type' => 'select', 'options' => ['合格', '不合格', '不适用'], 'required' => false],
                ['key' => 'operation_result', 'label' => '实际操作是否合格', 'type' => 'select', 'options' => ['合格', '不合格', '不适用'], 'required' => false],
                ['key' => 'written_score', 'label' => '笔试成绩', 'type' => 'number', 'required' => false],
                ['key' => 'host_department', 'label' => '主持考核部门', 'type' => 'department', 'required' => false],
                ['key' => 'assessment_date', 'label' => '考核时间', 'type' => 'date', 'required' => false],
            ]],
        ];
    }

    private static function prejobAssessmentSchema(): array
    {
        return [
            ['key' => 'trainee_name', 'label' => '姓名', 'type' => 'person', 'required' => true],
            ['key' => 'gender', 'label' => '性别', 'type' => 'select', 'options' => ['男', '女', '其他'], 'required' => false],
            ['key' => 'birth_month', 'label' => '出生年月', 'type' => 'text', 'required' => false],
            ['key' => 'work_start_date', 'label' => '参加工作时间', 'type' => 'date', 'required' => false],
            ['key' => 'education_level', 'label' => '文化程度', 'type' => 'text', 'required' => false],
            ['key' => 'major', 'label' => '所学专业', 'type' => 'text', 'required' => false],
            ['key' => 'current_position', 'label' => '现岗位名称', 'type' => 'text', 'required' => false],
            ['key' => 'prejob_training_items', 'label' => '岗前培训考核明细', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'training_content', 'label' => '培训内容', 'type' => 'textarea', 'required' => true],
                ['key' => 'completion_status', 'label' => '完成情况', 'type' => 'text', 'required' => false],
                ['key' => 'assessment_score', 'label' => '考核成绩', 'type' => 'text', 'required' => false],
                ['key' => 'remarks', 'label' => '备注', 'type' => 'text', 'required' => false],
            ]],
            ['key' => 'technical_manager_opinion', 'label' => '技术负责人意见', 'type' => 'textarea', 'required' => false],
            ['key' => 'technical_manager_date', 'label' => '技术负责人日期', 'type' => 'date', 'required' => false],
            ['key' => 'lab_director_opinion', 'label' => '实验室主任考核意见', 'type' => 'textarea', 'required' => false],
            ['key' => 'lab_director_date', 'label' => '实验室主任日期', 'type' => 'date', 'required' => false],
            ['key' => 'remarks', 'label' => '备注', 'type' => 'textarea', 'required' => false],
        ];
    }

    private static function trainingApplicationSchema(): array
    {
        return [
            ['key' => 'training_content', 'label' => '申请培训内容', 'type' => 'textarea', 'required' => true],
            ['key' => 'participants', 'label' => '参加人员', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'name', 'label' => '姓名', 'type' => 'person', 'required' => true],
                ['key' => 'department', 'label' => '部门', 'type' => 'department', 'required' => false],
                ['key' => 'signature', 'label' => '签名', 'type' => 'signature', 'required' => false],
            ]],
            ['key' => 'training_provider', 'label' => '培训单位', 'type' => 'text', 'required' => false],
            ['key' => 'training_place', 'label' => '培训地点', 'type' => 'text', 'required' => false],
            ['key' => 'training_time', 'label' => '培训时间', 'type' => 'text', 'required' => false],
            ['key' => 'estimated_cost', 'label' => '所需费用', 'type' => 'number', 'required' => false],
            ['key' => 'application_department', 'label' => '申请培训部门', 'type' => 'department', 'required' => false],
            ['key' => 'application_opinion', 'label' => '申请部门意见', 'type' => 'textarea', 'required' => false],
            ['key' => 'application_responsible_person', 'label' => '申请部门负责人', 'type' => 'person', 'required' => false],
            ['key' => 'application_date', 'label' => '申请日期', 'type' => 'date', 'required' => false],
            ['key' => 'audit_opinion', 'label' => '审核意见', 'type' => 'textarea', 'required' => false],
            ['key' => 'auditor', 'label' => '审核人', 'type' => 'person', 'required' => false],
            ['key' => 'audit_date', 'label' => '审核日期', 'type' => 'date', 'required' => false],
            ['key' => 'approval_opinion', 'label' => '批准意见', 'type' => 'textarea', 'required' => false],
            ['key' => 'approver', 'label' => '批准人', 'type' => 'person', 'required' => false],
            ['key' => 'approval_date', 'label' => '批准日期', 'type' => 'date', 'required' => false],
            ['key' => 'remarks', 'label' => '备注', 'type' => 'textarea', 'required' => false],
        ];
    }

    private static function personnelProfileSchema(): array
    {
        return [
            ['key' => 'hire_date', 'label' => '入职时间', 'type' => 'date', 'required' => false],
            ['key' => 'department', 'label' => '所属部门', 'type' => 'department', 'required' => false],
            ['key' => 'applied_position', 'label' => '应聘职位', 'type' => 'text', 'required' => false],
            ['key' => 'employee_name', 'label' => '姓名', 'type' => 'person', 'required' => true],
            ['key' => 'gender', 'label' => '性别', 'type' => 'select', 'options' => ['男', '女', '其他'], 'required' => false],
            ['key' => 'ethnicity', 'label' => '民族', 'type' => 'text', 'required' => false],
            ['key' => 'birth_month', 'label' => '出生年月', 'type' => 'text', 'required' => false],
            ['key' => 'height', 'label' => '身高', 'type' => 'text', 'required' => false],
            ['key' => 'native_place', 'label' => '籍贯', 'type' => 'text', 'required' => false],
            ['key' => 'graduate_school', 'label' => '毕业院校', 'type' => 'text', 'required' => false],
            ['key' => 'major', 'label' => '专业', 'type' => 'text', 'required' => false],
            ['key' => 'education_level', 'label' => '学历', 'type' => 'text', 'required' => false],
            ['key' => 'graduation_date', 'label' => '毕业时间', 'type' => 'date', 'required' => false],
            ['key' => 'political_status', 'label' => '政治面貌', 'type' => 'text', 'required' => false],
            ['key' => 'work_start_date', 'label' => '参加工作时间', 'type' => 'date', 'required' => false],
            ['key' => 'registered_address', 'label' => '户口所在地', 'type' => 'text', 'required' => false],
            ['key' => 'marital_status', 'label' => '婚姻状况', 'type' => 'text', 'required' => false],
            ['key' => 'id_number', 'label' => '身份证号码', 'type' => 'text', 'required' => false],
            ['key' => 'address', 'label' => '详细住址', 'type' => 'textarea', 'required' => false],
            ['key' => 'phone', 'label' => '联系方式', 'type' => 'text', 'required' => false],
            ['key' => 'backup_phone', 'label' => '备用联系方式', 'type' => 'text', 'required' => false],
            ['key' => 'qualification_certificate', 'label' => '专业技术资格证书', 'type' => 'text', 'required' => false],
            ['key' => 'email', 'label' => '电子邮箱', 'type' => 'text', 'required' => false],
            ['key' => 'education_items', 'label' => '教育及培训经历', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'period', 'label' => '起止年月', 'type' => 'text', 'required' => false],
                ['key' => 'school', 'label' => '学校名称', 'type' => 'text', 'required' => false],
                ['key' => 'major', 'label' => '所学专业', 'type' => 'text', 'required' => false],
            ]],
            ['key' => 'work_items', 'label' => '工作经历', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'period', 'label' => '起止年月', 'type' => 'text', 'required' => false],
                ['key' => 'company', 'label' => '工作单位', 'type' => 'text', 'required' => false],
                ['key' => 'position', 'label' => '岗位', 'type' => 'text', 'required' => false],
                ['key' => 'leave_time', 'label' => '何时离职', 'type' => 'text', 'required' => false],
                ['key' => 'witness', 'label' => '证明人及电话', 'type' => 'text', 'required' => false],
            ]],
            ['key' => 'family_items', 'label' => '家庭成员', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'relationship', 'label' => '称谓', 'type' => 'text', 'required' => false],
                ['key' => 'name', 'label' => '姓名', 'type' => 'text', 'required' => false],
                ['key' => 'birth_month', 'label' => '出生年月', 'type' => 'text', 'required' => false],
                ['key' => 'work_unit', 'label' => '工作单位及职务', 'type' => 'text', 'required' => false],
                ['key' => 'phone', 'label' => '联系电话', 'type' => 'text', 'required' => false],
            ]],
            ['key' => 'self_evaluation', 'label' => '自我评价', 'type' => 'textarea', 'required' => false],
            ['key' => 'commitment_signature', 'label' => '承诺签名', 'type' => 'signature', 'required' => false],
            ['key' => 'commitment_date', 'label' => '承诺日期', 'type' => 'date', 'required' => false],
        ];
    }

    private static function capabilityConfirmationSchema(): array
    {
        return [
            ['key' => 'employee_name', 'label' => '姓名', 'type' => 'person', 'required' => true],
            ['key' => 'department_position', 'label' => '部门/岗位', 'type' => 'text', 'required' => false],
            ['key' => 'hire_date', 'label' => '入职时间', 'type' => 'date', 'required' => false],
            ['key' => 'work_years', 'label' => '工作年限', 'type' => 'number', 'required' => false],
            ['key' => 'title', 'label' => '职称', 'type' => 'text', 'required' => false],
            ['key' => 'education_level', 'label' => '学历', 'type' => 'text', 'required' => false],
            ['key' => 'graduate_school', 'label' => '毕业院校', 'type' => 'text', 'required' => false],
            ['key' => 'major', 'label' => '专业', 'type' => 'text', 'required' => false],
            ['key' => 'experience_summary', 'label' => '工作经历和培训情况概述', 'type' => 'textarea', 'required' => false],
            ['key' => 'capability_items', 'label' => '能力确认情况', 'type' => 'repeatable_table', 'columns' => [
                ['key' => 'content', 'label' => '内容', 'type' => 'textarea', 'required' => true],
                ['key' => 'confirmation_method', 'label' => '确认方式', 'type' => 'text', 'required' => false],
                ['key' => 'result', 'label' => '结果', 'type' => 'select', 'options' => ['不合格', '合格', '良好', '优秀'], 'required' => false],
            ]],
            ['key' => 'confirmation_result', 'label' => '综合确认结果', 'type' => 'textarea', 'required' => false],
            ['key' => 'confirmer', 'label' => '确认人', 'type' => 'person', 'required' => false],
            ['key' => 'confirmation_date', 'label' => '确认日期', 'type' => 'date', 'required' => false],
            ['key' => 'authorization_result', 'label' => '授权结果', 'type' => 'textarea', 'required' => false],
            ['key' => 'authorizer', 'label' => '授权人', 'type' => 'person', 'required' => false],
            ['key' => 'authorization_date', 'label' => '授权日期', 'type' => 'date', 'required' => false],
        ];
    }

    private static function trainingEvaluationSchema(): array
    {
        return [
            ['key' => 'employee_name', 'label' => '姓名', 'type' => 'person', 'required' => true],
            ['key' => 'department', 'label' => '所在部门', 'type' => 'department', 'required' => false],
            ['key' => 'position', 'label' => '岗位', 'type' => 'text', 'required' => false],
            ['key' => 'training_nature', 'label' => '培训性质', 'type' => 'text', 'required' => false],
            ['key' => 'training_method', 'label' => '培训方式', 'type' => 'text', 'required' => false],
            ['key' => 'training_time', 'label' => '培训时间', 'type' => 'text', 'required' => false],
            ['key' => 'training_provider_or_trainer', 'label' => '培训单位/讲师', 'type' => 'text', 'required' => false],
            ['key' => 'certificate_number', 'label' => '证书编号', 'type' => 'text', 'required' => false],
            ['key' => 'training_main_content', 'label' => '培训的主要内容', 'type' => 'textarea', 'required' => false],
            ['key' => 'assessment_method', 'label' => '考核评价方式', 'type' => 'textarea', 'required' => false],
            ['key' => 'assessment_content', 'label' => '考核内容', 'type' => 'textarea', 'required' => false],
            ['key' => 'assessment_result', 'label' => '考核结果', 'type' => 'textarea', 'required' => false],
            ['key' => 'supervisor', 'label' => '监督员', 'type' => 'person', 'required' => false],
            ['key' => 'supervisor_date', 'label' => '监督日期', 'type' => 'date', 'required' => false],
            ['key' => 'evaluation_opinion', 'label' => '评价意见', 'type' => 'textarea', 'required' => false],
            ['key' => 'responsible_person', 'label' => '负责人', 'type' => 'person', 'required' => false],
            ['key' => 'responsible_date', 'label' => '负责人日期', 'type' => 'date', 'required' => false],
            ['key' => 'remarks', 'label' => '备注', 'type' => 'textarea', 'required' => false],
        ];
    }
}
