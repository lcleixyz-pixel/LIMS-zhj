<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Capa;
use app\model\CustomerComplaint;
use app\service\WorkflowService;
use app\service\FieldAuditService;
use app\service\ActionAuthorizationService;
use think\facade\Db;
use think\facade\Session;
use think\facade\View;

class Complaint extends BusinessBase
{
    protected string $modelClass = CustomerComplaint::class;
    protected string $viewPrefix = 'complaint';
    protected string $pageTitle = '客户投诉';
    protected array $writableFields = [
        'complaint_number',
        'customer_name',
        'contact',
        'received_date',
        'report_number',
        'assigned_to',
        'due_date',
        'description',
    ];
    protected array $validateMessages = [
        'complaint_number.require' => '投诉编号不能为空',
        'customer_name.require' => '客户名称不能为空',
        'description.require' => '投诉内容不能为空',
        'received_date.require' => '受理日期不能为空',
        'received_date.date' => '受理日期格式不正确',
        'due_date.date' => '处理期限格式不正确',
    ];

    protected function validationRules(array $data, ?string $recordId = null): array
    {
        $rules = [];
        foreach ([
            'complaint_number' => 'require',
            'customer_name' => 'require',
            'description' => 'require',
            'received_date' => 'require|date',
        ] as $field => $rule) {
            if ($recordId === null || array_key_exists($field, $data)) {
                $rules[$field] = $rule;
            }
        }
        if (array_key_exists('due_date', $data) && $data['due_date'] !== '') {
            $rules['due_date'] = 'date';
        }

        return $rules;
    }

    protected function assignFormContext(): void
    {
        $this->assignUsers();
        $this->assignStatusLabels('complaint');
    }

    public function index()
    {
        $query = CustomerComplaint::where('soft_delete', 0);
        $scope = ActionAuthorizationService::complaintVisibilityScope();
        if (!$scope['all']) {
            $visibleCreatorIds = [];
            if ($scope['site_ids'] !== []) {
                $visibleCreatorIds = array_map(
                    'strval',
                    Db::name('users')->alias('u')
                        ->join('employees e', 'e.id = u.employee_id')
                        ->whereIn('e.primary_site_id', $scope['site_ids'])
                        ->where('u.publish', 1)
                        ->where('u.soft_delete', 0)
                        ->where('e.publish', 1)
                        ->where('e.soft_delete', 0)
                        ->column('u.id')
                );
            }
            $query->where(function ($query) use ($scope, $visibleCreatorIds) {
                $query->where('created_by', $scope['user_id'])
                    ->whereOr('assigned_to', $scope['user_id']);
                if ($visibleCreatorIds !== []) {
                    $query->whereOr('created_by', 'in', $visibleCreatorIds);
                }
            });
        }

        $items = $query->order('created', 'desc')->paginate(20);
        View::assign('items', $items);
        View::assign('pages', $items->render());
        View::assign('pageTitle', $this->pageTitle);
        $this->assignIndexContext();

        return View::fetch($this->viewPrefix . '/index');
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->onlyWritable($this->request->post());
            if (empty($data['complaint_number'])) {
                $data['complaint_number'] = qms_next_number('CP', CustomerComplaint::class, 'complaint_number');
            }
            if (empty($data['received_date'])) {
                $data['received_date'] = date('Y-m-d');
            }
            $errors = $this->validateFormData($data);
            if ($errors !== []) {
                return $this->renderFormValidationFailure($data, $this->viewPrefix . '/add');
            }
            $model = $this->getModel();
            $model->save($data);
            Session::flash('success', '投诉已登记');

            return redirect($this->listRedirectUrl());
        }
        View::assign('pageTitle', $this->pageTitle . ' - 新增');
        $this->assignFormContext();

        return View::fetch($this->viewPrefix . '/add');
    }

    public function view()
    {
        $id = $this->request->param('id');
        $record = $this->findActiveRecord((string)$id);
        if (!$record) {
            abort(404);
        }
        $this->assignFormContext();
        View::assign('record', $record);
        View::assign('capa', $record->capa_id ? Capa::find($record->capa_id) : null);
        View::assign('fieldChangeLogs', FieldAuditService::displayLogsFor('CustomerComplaint', (string)$id));
        View::assign('pageTitle', $this->pageTitle . ' - 详情');

        return View::fetch($this->viewPrefix . '/view');
    }

    public function advance()
    {
        $id = $this->request->param('id');
        $record = $this->findActiveRecord((string)$id);
        if (!$record) {
            abort(404);
        }
        $flow = ['received' => 'investigating', 'investigating' => 'handling', 'handling' => 'responded', 'responded' => 'closed'];
        if ($this->request->isPost()) {
            $data = $this->request->post();
            Db::transaction(function () use ($record, $data, $flow): void {
                foreach (['investigation', 'handling', 'response'] as $field) {
                    if (!empty($data[$field])) {
                        $record->$field = $data[$field];
                    }
                }
                if (isset($flow[$record->status])) {
                    $record->status = $flow[$record->status];
                }
                if ($record->status === 'closed') {
                    $record->closed_date = date('Y-m-d');
                }
                $record->save();
            });
            Session::flash('success', '投诉状态已更新');

            return redirect('/complaint/view?id=' . $id);
        }
        $this->assignFormContext();
        View::assign('record', $record);

        return View::fetch($this->viewPrefix . '/advance');
    }

    public function createCapa()
    {
        $id = $this->request->param('id');
        $record = $this->findActiveRecord((string)$id);
        if (!$record || $record->capa_id) {
            Session::flash('error', '无法创建CAPA');

            return redirect('/complaint/view?id=' . $id);
        }
        $capa = WorkflowService::createCapaFromSource(
            'complaint',
            $record->id,
            $record->description,
            WorkflowService::resolveCapaSourceId('complaint'),
            $record->assigned_to,
            $record->due_date
        );
        Session::flash('success', "已创建 CAPA {$capa->capa_number}");

        return redirect('/capa/view?id=' . $capa->id);
    }
}
