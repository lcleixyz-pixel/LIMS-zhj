<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\QmsElement;
use app\service\QmsElementService;
use think\exception\HttpException;
use think\facade\Session;
use think\facade\View;

class PlanningElement extends BaseController
{
    public function index()
    {
        View::assign('columnLabels', QmsElementService::traceabilityColumnLabels());
        View::assign('rows', QmsElementService::coverageStats());

        return View::fetch('planning_element/index');
    }

    public function view()
    {
        $detail = QmsElementService::elementDetail((string)$this->request->param('id', ''));
        if ($detail === []) {
            throw new HttpException(404, '体系要素不存在');
        }

        View::assign('detail', $detail);
        View::assign('businessModuleOptions', QmsElementService::businessModuleOptionsForElement((string)$detail['element']->id));

        return View::fetch('planning_element/view');
    }

    public function mapBusinessModule()
    {
        $elementId = trim((string)$this->request->post('element_id', ''));
        $moduleId = trim((string)$this->request->post('module_id', ''));
        $relationType = (string)$this->request->post('relation_type', 'supporting');
        $note = trim((string)$this->request->post('note', ''));

        try {
            QmsElementService::mapBusinessModuleToElement($moduleId, $elementId, $relationType, $note);
            Session::flash('success', '运行模块映射已保存。');
        } catch (\Throwable $exception) {
            Session::flash('error', $exception->getMessage());
        }

        return redirect($elementId !== '' ? '/planning/elements/view?id=' . $elementId : '/planning/elements');
    }

    public function edit()
    {
        $element = QmsElement::where('soft_delete', 0)->find($this->request->param('id'));
        if (!$element) {
            throw new HttpException(404, '体系要素不存在');
        }

        if ($this->request->isPost()) {
            $element->save([
                'name' => trim((string)$this->request->post('name', '')),
                'summary' => trim((string)$this->request->post('summary', '')),
                'element_type' => (string)$this->request->post('element_type', 'management'),
                'applicability' => (string)$this->request->post('applicability', 'applicable'),
                'applicability_note' => trim((string)$this->request->post('applicability_note', '')),
                'status' => (string)$this->request->post('status', 'effective'),
                'sort_order' => (int)$this->request->post('sort_order', 0),
            ]);
            Session::flash('success', '体系要素已保存。');

            return redirect('/planning/elements/view?id=' . $element->id);
        }

        View::assign('element', $element);

        return View::fetch('planning_element/edit');
    }

    public function seed()
    {
        $summary = QmsElementService::seedAll();
        Session::flash('success', '策划骨架已初始化：要素 ' . (int)($summary['elements']['elements'] ?? 0)
            . '，条款 ' . (int)($summary['sources']['clauses'] ?? 0)
            . '，手册章节 ' . (int)($summary['manual_sections']['manual_sections'] ?? 0) . '。');

        return redirect('/planning/elements');
    }
}
