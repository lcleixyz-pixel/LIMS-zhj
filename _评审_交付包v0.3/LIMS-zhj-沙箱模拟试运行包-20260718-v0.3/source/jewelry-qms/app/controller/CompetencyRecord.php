<?php
declare(strict_types=1);

namespace app\controller;

use app\model\CompetencyRecord as CompetencyRecordModel;
use app\model\Employee;
use app\model\User;
use think\facade\View;

class CompetencyRecord extends BusinessBase
{
    protected string $modelClass = CompetencyRecordModel::class;
    protected string $viewPrefix = 'competency_record';
    protected string $pageTitle = '能力确认';
    protected array $writableFields = [
        'employee_id',
        'test_item',
        'method_standard',
        'assessment_date',
        'assessor_id',
        'result',
        'authorization_scope',
        'valid_until',
    ];
    protected array $validateMessages = [
        'employee_id.require' => '请选择被评价人员',
        'test_item.require' => '检测项目不能为空',
        'assessment_date.require' => '评价日期不能为空',
        'assessment_date.date' => '评价日期格式不正确',
        'assessor_id.require' => '请选择评价人',
        'valid_until.date' => '有效期格式不正确',
    ];
    protected array $viewFieldLabels = [
        'test_item' => '检测项目',
        'method_standard' => '方法/标准',
        'assessment_date' => '评价日期',
        'assessor_id' => '评价人',
        'result' => '评价结果',
        'authorization_scope' => '授权范围',
        'valid_until' => '有效期至',
    ];

    protected function validationRules(array $data, ?string $recordId = null): array
    {
        $rules = [];
        foreach ([
            'employee_id' => 'require',
            'test_item' => 'require',
            'assessment_date' => 'require|date',
            'assessor_id' => 'require',
        ] as $field => $rule) {
            if ($recordId === null || array_key_exists($field, $data)) {
                $rules[$field] = $rule;
            }
        }
        if (array_key_exists('valid_until', $data) && $data['valid_until'] !== '') {
            $rules['valid_until'] = 'date';
        }

        return $rules;
    }

    protected function assignFormContext(): void
    {
        $this->assignEmployees();
        $this->assignUsers();
        View::assign('resultOptions', [
            'qualified' => '合格',
            'unqualified' => '不合格',
            'supervised' => '监督下操作',
            'pending' => '待评价',
        ]);
    }

    protected function assignIndexContext(): void
    {
        $this->assignFormContext();
    }

    protected function formatViewFieldValue(string $field, mixed $value): string
    {
        if ($field === 'employee_id' && $value) {
            return (string)(Employee::where('id', (string)$value)->value('name') ?: $value);
        }
        if ($field === 'assessor_id' && $value) {
            return (string)(User::where('id', (string)$value)->value('name') ?: $value);
        }
        if ($field === 'result' && $value) {
            $labels = [
                'qualified' => '合格',
                'unqualified' => '不合格',
                'supervised' => '监督下操作',
                'pending' => '待评价',
            ];

            return $labels[(string)$value] ?? (string)$value;
        }

        return parent::formatViewFieldValue($field, $value);
    }
}
