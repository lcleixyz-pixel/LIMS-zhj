<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\QmsAgentSuggestion;
use app\model\QmsClause;
use app\model\QmsElement;
use app\model\QmsSource;
use app\service\QmsElementService;
use RuntimeException;
use think\facade\Session;
use think\facade\View;

class PlanningDashboard extends BaseController
{
    public function index()
    {
        $rows = QmsElementService::coverageStats();

        View::assign('stats', [
            'source_count' => QmsSource::where('soft_delete', 0)->where('status', 'published')->count(),
            'clause_count' => QmsClause::where('soft_delete', 0)->count(),
            'element_count' => QmsElement::where('soft_delete', 0)->count(),
            'open_suggestion_count' => QmsAgentSuggestion::where('status', 'open')->count(),
        ]);
        View::assign('rows', array_slice($rows, 0, 12));
        View::assign('gapCount', array_sum(array_column($rows, 'gap_count')));
        View::assign('clauseMappingSuggestions', QmsElementService::openClauseMappingSuggestions());
        View::assign('procedureRecordSuggestions', QmsElementService::openProcedureRecordSuggestions());
        View::assign('recordSchemaSuggestions', QmsElementService::openRecordSchemaSuggestions());

        return View::fetch('planning_dashboard/index');
    }

    public function reviewSuggestion()
    {
        $redirectTo = $this->safePlanningRedirect((string)$this->request->post('redirect_to', '/planning/index'));
        if (!$this->request->isPost()) {
            Session::flash('warning', '请从建议列表处理智能体建议。');

            return redirect($redirectTo);
        }

        try {
            QmsElementService::reviewAgentSuggestion(
                (string)$this->request->post('suggestion_id', ''),
                (string)$this->request->post('status', ''),
                (string)$this->request->post('review_note', '')
            );
            Session::flash('success', '智能体建议已记录人工处理结果。');
        } catch (RuntimeException $exception) {
            Session::flash('warning', $exception->getMessage());
        }

        return redirect($redirectTo);
    }

    public function batchDecide()
    {
        $redirectTo = $this->safePlanningRedirect((string)$this->request->post('redirect_to', '/planning/index'));
        if (!$this->request->isPost()) {
            Session::flash('warning', '请从建议池提交批量采纳/驳回。');

            return redirect($redirectTo);
        }

        $decision = trim((string)$this->request->post('decision', ''));
        if (!in_array($decision, ['accepted', 'rejected'], true)) {
            Session::flash('warning', '批量处置动作无效，仅支持 accepted / rejected。');

            return redirect($redirectTo);
        }

        $ids = $this->request->post('suggestion_ids/a', []);
        if (!is_array($ids) || $ids === []) {
            Session::flash('warning', '请至少选择一条建议。');

            return redirect($redirectTo);
        }

        $note = trim((string)$this->request->post('review_note', '批量处置'));
        $ok = 0;
        $errors = [];
        foreach ($ids as $suggestionId) {
            try {
                QmsElementService::reviewAgentSuggestion((string)$suggestionId, $decision, $note);
                $ok++;
            } catch (RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        if ($errors === []) {
            Session::flash('success', '已批量处理建议 ' . $ok . ' 条。');
        } else {
            Session::flash('warning', '批量处理完成 ' . $ok . ' 条；失败：' . implode('；', array_slice($errors, 0, 3)));
        }

        return redirect($redirectTo);
    }

    private function safePlanningRedirect(string $path): string
    {
        $path = trim($path);
        if (str_starts_with($path, '/planning/')) {
            return $path;
        }

        return '/planning/index';
    }
}
