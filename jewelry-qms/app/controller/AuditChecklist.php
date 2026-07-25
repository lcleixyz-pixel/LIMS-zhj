<?php
declare(strict_types=1);

namespace app\controller;

use app\model\AuditChecklist as AuditChecklistModel;
use app\model\AuditSchedule;
use app\service\AuditClosureService;
use DomainException;
use think\exception\HttpException;
use think\facade\Config;
use think\facade\View;

class AuditChecklist extends BusinessBase
{
    protected string $modelClass = AuditChecklistModel::class;
    protected string $viewPrefix = 'audit_checklist';
    protected string $pageTitle = '审核检查表';
    protected array $writableFields = [
        'audit_schedule_id', 'clause', 'check_item', 'result', 'evidence',
    ];
    protected array $validateRules = [
        'audit_schedule_id' => 'require',
        'check_item' => 'require',
        'result' => 'require',
        'evidence' => 'require',
    ];
    protected array $viewFieldLabels = [
        'audit_schedule_id' => '审核日程',
        'clause' => '条款',
        'check_item' => '检查项',
        'result' => '检查结果',
        'evidence' => '客观证据',
    ];

    protected function findActiveRecord(string $id): ?object
    {
        return AuditChecklistModel::alias('c')
            ->join('audit_schedules s', 's.id = c.audit_schedule_id')
            ->join('audit_plans p', 'p.id = s.audit_plan_id')
            ->where('c.id', $id)
            ->where('p.company_id', (string)Config::get('qms.company_id'))
            ->where('c.soft_delete', 0)
            ->where('s.soft_delete', 0)
            ->where('p.soft_delete', 0)
            ->field('c.*')
            ->find();
    }

    public function index()
    {
        $items = AuditChecklistModel::alias('c')
            ->join('audit_schedules s', 's.id = c.audit_schedule_id')
            ->join('audit_plans p', 'p.id = s.audit_plan_id')
            ->where('p.company_id', (string)Config::get('qms.company_id'))
            ->where('c.soft_delete', 0)
            ->where('s.soft_delete', 0)
            ->where('p.soft_delete', 0)
            ->field('c.*')
            ->order('c.created', 'desc')
            ->paginate(20);
        View::assign('items', $items);
        View::assign('pages', $items->render());
        View::assign('pageTitle', $this->pageTitle);
        $this->assignIndexContext();

        return View::fetch($this->viewPrefix . '/index');
    }

    protected function formatViewFieldValue(string $field, mixed $value): string
    {
        if ($field === 'result') {
            return qms_status_label('audit_checklist', (string)$value);
        }

        return parent::formatViewFieldValue($field, $value);
    }

    public function add()
    {
        $scheduleId = trim((string)$this->request->post(
            'audit_schedule_id',
            $this->request->param('audit_schedule_id', '')
        ));
        if ($scheduleId !== '') {
            $this->assertScheduleWritable($scheduleId);
        }

        return parent::add();
    }

    public function edit()
    {
        return parent::edit();
    }

    public function delete()
    {
        return parent::delete();
    }

    protected function assignFormContext(): void
    {
        $scheduleId = $this->request->param('audit_schedule_id', '');
        View::assign(
            'auditSchedules',
            AuditSchedule::alias('s')
                ->join('audit_plans p', 'p.id = s.audit_plan_id')
                ->where('p.company_id', (string)Config::get('qms.company_id'))
                ->where('p.soft_delete', 0)
                ->where('s.soft_delete', 0)
                ->where('s.status', '<>', 'completed')
                ->field('s.*')
                ->select()
        );
        View::assign('defaultScheduleId', $scheduleId);
        View::assign('resultOptions', [
            'conform' => '符合',
            'nonconform' => '不符合',
            'observation' => '观察项',
            'na' => '不适用',
        ]);
    }

    protected function assignIndexContext(): void
    {
        $this->assignFormContext();
    }

    private function assertScheduleWritable(string $scheduleId): void
    {
        try {
            AuditClosureService::assertScheduleWritable($scheduleId);
        } catch (DomainException $exception) {
            throw new HttpException(409, $exception->getMessage());
        }
    }
}
