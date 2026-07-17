<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\ExternalEvidenceReference as ExternalEvidenceReferenceModel;
use app\service\ExternalEvidenceReferenceService;
use InvalidArgumentException;
use think\facade\Session;
use think\facade\View;

class ExternalEvidenceReference extends BaseController
{
    public function index()
    {
        $query = ExternalEvidenceReferenceModel::where('soft_delete', 0);
        $subjectType = trim((string)$this->request->get('subject_type', ''));
        if ($subjectType !== '') {
            $query->where('subject_type', $subjectType);
        }
        $items = $query->order('cited_at', 'desc')->paginate(30);
        View::assign('items', $items);
        View::assign('pages', $items->render());
        View::assign('subjectType', $subjectType);

        return View::fetch('external_evidence_reference/index');
    }

    public function add()
    {
        $subjectType = trim((string)$this->request->post('subject_type', ''));
        $subjectId = trim((string)$this->request->post('subject_id', ''));
        $returnUrl = ExternalEvidenceReferenceService::subjectUrl($subjectType, $subjectId);

        try {
            ExternalEvidenceReferenceService::create($subjectType, $subjectId, $this->request->post());
            Session::flash('success', '外部业务证据引用已登记；系统仅保存引用元数据，不复制业务正文。');
        } catch (InvalidArgumentException $exception) {
            Session::flash('error', $exception->getMessage());
        }

        return redirect($returnUrl);
    }
}
