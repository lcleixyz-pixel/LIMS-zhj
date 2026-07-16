<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\QmsExternalChangeEvent;
use app\model\QmsSource;
use app\service\ExternalChangeEventService;
use app\service\FieldAuditService;
use app\service\FileAttachmentService;
use app\service\FileService;
use think\exception\HttpException;
use think\facade\Session;
use think\facade\View;

class PlanningChangeEvent extends BaseController
{
    public function index()
    {
        $query = QmsExternalChangeEvent::where('soft_delete', 0);
        $status = trim((string)$this->request->param('status', ''));
        $sourceKind = trim((string)$this->request->param('source_kind', ''));
        $keyword = trim((string)$this->request->param('keyword', ''));

        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($sourceKind !== '') {
            $query->where('source_kind', $sourceKind);
        }
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('event_code', '%' . $keyword . '%')
                    ->whereOr('source_name', 'like', '%' . $keyword . '%')
                    ->whereOr('announcement_number', 'like', '%' . $keyword . '%')
                    ->whereOr('event_summary', 'like', '%' . $keyword . '%');
            });
        }

        $items = $query->order('created', 'desc')->order('event_code', 'desc')->paginate(20);
        View::assign('items', $items);
        View::assign('pages', $items->render());
        $this->assignCommonContext([
            'status' => $status,
            'source_kind' => $sourceKind,
            'keyword' => $keyword,
        ]);

        return View::fetch('planning_change_event/index');
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $raw = $this->request->post();
            $data = ExternalChangeEventService::normalizeInput($raw, true);
            $errors = ExternalChangeEventService::validateData($data, true);
            if ($errors !== []) {
                Session::flash('validation_errors', $errors);
                View::assign('record', array_merge($this->emptyRecord(), $data));
                $this->assignCommonContext();

                return View::fetch('planning_change_event/add');
            }

            $event = new QmsExternalChangeEvent();
            $event->save($data);
            Session::flash('success', '外部变更事件已登记，后续影响分析和修订仍需人工确认。');

            return redirect('/planning/change-events/view?id=' . $event->id);
        }

        View::assign('record', $this->emptyRecord());
        $this->assignCommonContext();

        return View::fetch('planning_change_event/add');
    }

    public function edit()
    {
        $event = $this->findEvent();
        if ($this->request->isPost()) {
            $data = ExternalChangeEventService::normalizeInput($this->request->post(), false);
            $errors = ExternalChangeEventService::validateData(array_merge($event->toArray(), $data), false);
            if ($errors !== []) {
                Session::flash('validation_errors', $errors);
                if (method_exists($event, 'setAttrs')) {
                    $event->setAttrs($data);
                }
                View::assign('record', $event);
                $this->assignCommonContext();

                return View::fetch('planning_change_event/edit');
            }

            $event->save($data);
            Session::flash('success', '变更事件信息已更新。');

            return redirect('/planning/change-events/view?id=' . $event->id);
        }

        View::assign('record', $event);
        $this->assignCommonContext();

        return View::fetch('planning_change_event/edit');
    }

    public function view()
    {
        $event = $this->findEvent();
        View::assign('record', $event);
        View::assign('oldSource', $event->old_source_id ? QmsSource::find($event->old_source_id) : null);
        View::assign('newSource', $event->new_source_id ? QmsSource::find($event->new_source_id) : null);
        View::assign('attachments', FileAttachmentService::attachmentsFor('QmsExternalChangeEvent', (string)$event->id));
        View::assign('fieldChangeLogs', FieldAuditService::logsFor('QmsExternalChangeEvent', (string)$event->id));
        $this->assignCommonContext();

        return View::fetch('planning_change_event/view');
    }

    public function transition()
    {
        $event = $this->findEvent((string)$this->request->post('id', ''));
        try {
            ExternalChangeEventService::transition(
                $event,
                (string)$this->request->post('action', ''),
                trim((string)$this->request->post('close_reason', ''))
            );
            Session::flash('success', '事件状态已更新为：' . qms_status_label('planning_change_event', (string)$event->status));
        } catch (\Throwable $exception) {
            Session::flash('error', '状态更新失败：' . $exception->getMessage());
        }

        return redirect('/planning/change-events/view?id=' . $event->id);
    }

    public function uploadAttachment()
    {
        $event = $this->findEvent((string)$this->request->post('id', ''));
        $attachment = FileAttachmentService::upload(
            $_FILES['event_file'] ?? [],
            'QmsExternalChangeEvent',
            (string)$event->id,
            'qms-change-events',
            trim((string)$this->request->post('comment', ''))
        );
        Session::flash($attachment ? 'success' : 'error', $attachment ? '公告/查新附件已上传。' : '附件上传失败，请检查格式和大小。');

        return redirect('/planning/change-events/view?id=' . $event->id);
    }

    public function downloadAttachment()
    {
        $event = $this->findEvent((string)$this->request->param('id', ''));
        $fileId = (string)$this->request->param('file_id', '');
        $attachment = FileAttachmentService::findAttachment($fileId, 'QmsExternalChangeEvent', (string)$event->id);
        if (!$attachment) {
            throw new HttpException(404, '附件不存在');
        }

        FileService::download((string)$attachment->file_dir, (string)$attachment->file_details);
    }

    private function assignCommonContext(array $filters = []): void
    {
        $filters = array_merge([
            'status' => '',
            'source_kind' => '',
            'keyword' => '',
        ], $filters);
        View::assign('pageTitle', '外部变更事件');
        View::assign('statusLabels', ExternalChangeEventService::statusLabels());
        View::assign('sourceKindLabels', ExternalChangeEventService::sourceKindLabels());
        View::assign('filters', $filters);
        View::assign('sources', QmsSource::where('soft_delete', 0)->order('source_code', 'asc')->select());
    }

    private function findEvent(string $id = ''): QmsExternalChangeEvent
    {
        $eventId = $id !== '' ? $id : (string)$this->request->param('id', '');
        $event = QmsExternalChangeEvent::where('soft_delete', 0)->find($eventId);
        if (!$event) {
            throw new HttpException(404, '变更事件不存在');
        }

        return $event;
    }

    private function emptyRecord(): array
    {
        return [
            'event_code' => '',
            'source_kind' => 'cnas',
            'source_name' => '',
            'source_url' => '',
            'announcement_number' => '',
            'old_source_id' => '',
            'new_source_id' => '',
            'old_version' => '',
            'new_version' => '',
            'published_date' => '',
            'effective_date' => '',
            'event_summary' => '',
            'status' => ExternalChangeEventService::STATUS_REGISTERED,
            'graph_snapshot_hash' => ExternalChangeEventService::currentGraphSnapshotHash(),
        ];
    }
}
