<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\QmsClause;
use app\model\QmsClauseText;
use app\model\QmsElement;
use app\model\QmsSource;
use app\service\QmsElementService;
use app\service\QmsPlanningImportService;
use RuntimeException;
use think\exception\HttpException;
use think\facade\Db;
use think\facade\Session;
use think\facade\View;

class PlanningClause extends BaseController
{
    public function index()
    {
        $query = QmsClause::where('soft_delete', 0);
        $sourceId = trim((string)$this->request->param('source_id', ''));
        $keyword = trim((string)$this->request->param('keyword', ''));

        if ($sourceId !== '') {
            $query->where('source_id', $sourceId);
        }
        if ($keyword !== '') {
            $textClauseIds = QmsClauseText::where('soft_delete', 0)
                ->where('original_text', 'like', '%' . $keyword . '%')
                ->column('clause_id');
            $query->where(function ($q) use ($keyword, $textClauseIds) {
                $q->where('clause_number', 'like', '%' . $keyword . '%')
                    ->whereOr('title', 'like', '%' . $keyword . '%');
                if ($textClauseIds !== []) {
                    $q->whereOr('id', 'in', $textClauseIds);
                }
            });
        }

        $items = $query->order('source_id', 'asc')->order('clause_number', 'asc')->paginate([
            'list_rows' => 30,
            'query' => $this->request->get(),
        ]);
        $sourceMap = QmsSource::where('soft_delete', 0)->column('source_code', 'id');
        foreach ($items as $item) {
            $item->setAttr('source_code', $sourceMap[(string)$item->source_id] ?? (string)$item->source_id);
            $item->setAttr('sort_hint', QmsPlanningImportService::clauseDisplaySortToken((string)$item->clause_number));
        }

        View::assign('items', $items);
        View::assign('pages', $items->render());
        View::assign('sources', QmsSource::where('soft_delete', 0)->order('source_code', 'asc')->select());
        View::assign('filter', ['source_id' => $sourceId, 'keyword' => $keyword]);

        return View::fetch('planning_clause/index');
    }

    public function view()
    {
        $clause = QmsClause::where('soft_delete', 0)->find($this->request->param('id'));
        if (!$clause) {
            throw new HttpException(404, '条款不存在');
        }

        View::assign('clause', $clause);
        View::assign('source', QmsSource::where('soft_delete', 0)->find((string)$clause->source_id));
        View::assign('text', QmsClauseText::where('clause_id', (string)$clause->id)->where('soft_delete', 0)->find());
        View::assign('elements', Db::table('qms_element_clause_links')
            ->alias('l')
            ->join('qms_elements e', 'e.id = l.element_id')
            ->where('l.clause_id', (string)$clause->id)
            ->where('l.soft_delete', 0)
            ->field('e.id,e.name,e.element_type,l.mapping_type,l.is_primary,l.note')
            ->select());
        View::assign('availableElements', QmsElement::where('soft_delete', 0)
            ->order('sort_order', 'asc')
            ->order('name', 'asc')
            ->select());
        View::assign('structuredBlockEvidence', QmsElementService::clauseStructuredBlockEvidence((string)$clause->id));

        return View::fetch('planning_clause/view');
    }

    public function map()
    {
        $clauseId = (string)$this->request->post('clause_id', '');
        $redirectTo = $clauseId !== '' ? '/planning/clauses/view?id=' . urlencode($clauseId) : '/planning/clauses';
        if (!$this->request->isPost()) {
            Session::flash('warning', '请从条款详情页提交人工映射。');

            return redirect($redirectTo);
        }

        try {
            QmsElementService::mapClauseToElement(
                $clauseId,
                (string)$this->request->post('element_id', ''),
                (string)$this->request->post('mapping_type', 'supplement'),
                (string)$this->request->post('note', '')
            );
            Session::flash('success', '条款已人工映射到体系要素。');
        } catch (RuntimeException $exception) {
            Session::flash('error', $exception->getMessage());
        }

        return redirect($redirectTo);
    }

    public function localElement()
    {
        $clauseId = (string)$this->request->post('clause_id', '');
        $redirectTo = $clauseId !== '' ? '/planning/clauses/view?id=' . urlencode($clauseId) : '/planning/clauses';
        if (!$this->request->isPost()) {
            Session::flash('warning', '请从条款详情页提交本地补充要素。');

            return redirect($redirectTo);
        }

        try {
            QmsElementService::createLocalSupplementElementForClause($clauseId, [
                'name' => (string)$this->request->post('name', ''),
                'summary' => (string)$this->request->post('summary', ''),
                'element_type' => (string)$this->request->post('element_type', 'management'),
                'sort_order' => (int)$this->request->post('sort_order', 9900),
                'note' => (string)$this->request->post('note', ''),
            ]);
            Session::flash('success', '本地补充要素已创建并关联当前条款。');
        } catch (RuntimeException $exception) {
            Session::flash('error', $exception->getMessage());
        }

        return redirect($redirectTo);
    }
}
