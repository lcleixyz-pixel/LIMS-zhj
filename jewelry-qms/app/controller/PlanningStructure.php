<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\GovernedTrialResolvedDocumentService;
use app\service\QmsDocumentStructureService;
use app\service\QmsFileGovernanceWorkbenchService;
use app\service\QmsGovernanceQueueService;
use app\service\QmsReadableMarkdownService;
use think\exception\HttpException;
use think\facade\Session;
use think\facade\View;

class PlanningStructure extends BaseController
{
    private const STEP_LABELS = ['原始文件', 'Markdown结构', '内容块', '块级追溯', '渲染输出'];

    public function index()
    {
        View::assign('layers', QmsDocumentStructureService::structureLayerDefinitions());
        View::assign('rows', QmsDocumentStructureService::structuredDocumentRows());
        View::assign('coverage', QmsDocumentStructureService::controlledDocumentStructureCoverage());
        View::assign('recordCoverage', QmsDocumentStructureService::procedureRecordRequirementCoverage());
        View::assign('recordSchemaCoverage', QmsDocumentStructureService::recordRequirementSchemaCoverage());
        View::assign('stepLabels', self::STEP_LABELS);

        return View::fetch('planning_structure/index');
    }

    public function view()
    {
        $detail = QmsDocumentStructureService::structuredDocumentDetail((string)$this->request->param('id', ''));
        if ($detail === []) {
            throw new HttpException(404, '结构化文件不存在');
        }

        View::assign('detail', $detail);
        View::assign('stepLabels', self::STEP_LABELS);
        View::assign(
            'resolvedArtifacts',
            GovernedTrialResolvedDocumentService::resolvedArtifactLinks($detail['document'])
        );

        return View::fetch('planning_structure/view');
    }

    public function workbench()
    {
        $workbench = QmsFileGovernanceWorkbenchService::detail(
            (string)$this->request->param('id', ''),
            (string)Session::get('user.id', '')
        );
        if ($workbench === []) {
            throw new HttpException(404, '结构化文件不存在');
        }

        View::assign('workbench', $workbench);

        return View::fetch('planning_structure/workbench');
    }

    public function governanceQueue()
    {
        $queue = QmsGovernanceQueueService::listing([
            'status' => (string)$this->request->param('status', 'all'),
            'keyword' => trim((string)$this->request->param('keyword', '')),
        ], (string)Session::get('user.id', ''));

        View::assign('queue', $queue);

        return View::fetch('planning_structure/governance_queue');
    }

    public function nextGovernance()
    {
        $next = QmsGovernanceQueueService::nextUnresolved(
            (string)$this->request->param('current_id', ''),
            (string)Session::get('user.id', '')
        );
        if ($next === []) {
            Session::flash('success', '当前没有其他未完成文件。');

            return redirect('/planning/structures/governance-queue');
        }

        return redirect(
            '/planning/structures/workbench?id='
            . rawurlencode((string)$next['structured_id'])
        );
    }

    public function resolvedArtifact()
    {
        $artifact = GovernedTrialResolvedDocumentService::resolvedArtifact(
            (string)$this->request->param('id', ''),
            (string)$this->request->param('kind', '')
        );
        if ($artifact === []) {
            throw new HttpException(404, '治理解析稿材料不存在或不在允许目录内');
        }
        $readableParts = QmsReadableMarkdownService::separateGovernancePreamble((string)$artifact['content']);
        $artifact['content_html'] = QmsReadableMarkdownService::toHtml($readableParts['body']);
        $artifact['technical_content_html'] = $readableParts['technical'] !== ''
            ? QmsReadableMarkdownService::toHtml($readableParts['technical'])
            : '';
        View::assign('artifact', $artifact);

        return View::fetch('planning_structure/resolved_artifact');
    }

    public function editBlock()
    {
        $detail = QmsDocumentStructureService::blockEditDetail((string)$this->request->param('id', ''));
        if ($detail === []) {
            Session::flash('warning', '该结构化内容块已不存在，可能来自历史组合包快照；现行内容请从结构化文件或组合包页面重新进入。');

            return redirect('/planning/structures/package');
        }

        View::assign('detail', $detail);

        return View::fetch('planning_structure/block_edit');
    }

    public function reviewLinks()
    {
        $detail = QmsDocumentStructureService::blockTraceReviewDetail((string)$this->request->param('block_id', ''));
        if ($detail === []) {
            Session::flash('warning', '该结构化内容块已不存在，可能来自历史组合包快照；现行追溯请从结构化文件或组合包页面重新进入。');

            return redirect('/planning/structures/package');
        }

        View::assign('detail', $detail);

        return View::fetch('planning_structure/link_review');
    }

    public function saveLink()
    {
        $blockId = (string)$this->request->post('block_id', '');
        try {
            QmsDocumentStructureService::upsertBlockTraceLink($blockId, $this->request->post());
            Session::flash('success', '追溯关系已保存。');

            return redirect('/planning/structures/links/review?block_id=' . $blockId);
        } catch (\Throwable $exception) {
            Session::flash('error', $exception->getMessage());

            return $blockId !== ''
                ? redirect('/planning/structures/links/review?block_id=' . $blockId)
                : redirect('/planning/structures');
        }
    }

    public function deleteLink()
    {
        $blockId = (string)$this->request->post('block_id', '');
        try {
            QmsDocumentStructureService::deleteBlockTraceLink(
                (string)$this->request->post('link_id', ''),
                (string)$this->request->post('review_note', '')
            );
            Session::flash('success', '追溯关系已删除。');

            return redirect('/planning/structures/links/review?block_id=' . $blockId);
        } catch (\Throwable $exception) {
            Session::flash('error', $exception->getMessage());

            return $blockId !== ''
                ? redirect('/planning/structures/links/review?block_id=' . $blockId)
                : redirect('/planning/structures');
        }
    }

    public function saveReferenceMatch()
    {
        $structuredId = (string)$this->request->post('structured_document_id', '');
        try {
            QmsDocumentStructureService::saveReferenceProcedureManualMatch(
                (string)$this->request->post('reference_title', ''),
                (string)$this->request->post('procedure_document_id', ''),
                (string)$this->request->post('review_note', '')
            );
            Session::flash('success', '参考程序人工匹配已保存，并生成对照建议。');

            return redirect('/planning/structures/view?id=' . $structuredId);
        } catch (\Throwable $exception) {
            Session::flash('error', $exception->getMessage());

            return $structuredId !== ''
                ? redirect('/planning/structures/view?id=' . $structuredId)
                : redirect('/planning/structures');
        }
    }

    public function updateBlock()
    {
        try {
            $result = QmsDocumentStructureService::updateBlockMarkdown(
                (string)$this->request->post('id', ''),
                (string)$this->request->post('markdown', ''),
                (string)$this->request->post('revision_note', '')
            );
            $document = (array)($result['structured_document'] ?? []);
            Session::flash('success', '内容块已更新，并重新渲染归档：' . (string)($document['rendered_file_path'] ?? ''));

            return redirect('/planning/structures/view?id=' . (string)($document['id'] ?? ''));
        } catch (\Throwable $exception) {
            Session::flash('error', $exception->getMessage());

            return redirect('/planning/structures');
        }
    }

    public function publishDocument()
    {
        try {
            $result = QmsDocumentStructureService::publishStructuredDocument(
                (string)$this->request->post('id', ''),
                (string)$this->request->post('publish_note', '')
            );
            $document = (array)($result['structured_document'] ?? []);
            Session::flash('success', '结构化文件已发布：' . (string)($document['doc_number'] ?? ''));

            return redirect('/planning/structures/view?id=' . (string)($document['id'] ?? ''));
        } catch (\Throwable $exception) {
            Session::flash('error', $exception->getMessage());

            return redirect('/planning/structures');
        }
    }

    public function refreshSource()
    {
        try {
            $result = QmsDocumentStructureService::refreshStructuredDocumentFromSource(
                (string)$this->request->post('id', ''),
                (string)$this->request->post('refresh_note', '')
            );
            $document = (array)($result['structured_document'] ?? []);
            Session::flash('success', '已从受控源文件重建结构：内容块 ' . (int)($result['blocks'] ?? 0)
                . '，块级追溯 ' . (int)($result['links'] ?? 0)
                . '，等待复核发布。');

            return redirect('/planning/structures/view?id=' . (string)($document['id'] ?? ''));
        } catch (\Throwable $exception) {
            Session::flash('error', $exception->getMessage());

            return redirect('/planning/structures');
        }
    }

    public function package()
    {
        View::assign('summary', QmsDocumentStructureService::systemPackageSummary());
        View::assign('impactRows', QmsDocumentStructureService::latestSystemPackageChangeImpactRows());
        View::assign('blockTraceRows', QmsDocumentStructureService::latestSystemPackageBlockTraceRows());

        return View::fetch('planning_structure/package');
    }

    public function changeImpact()
    {
        View::assign('preview', QmsDocumentStructureService::changeControlImpactPreview(
            (string)$this->request->param('id', ''),
            (string)$this->request->param('revision_note', '')
        ));

        return View::fetch('planning_structure/change_impact');
    }

    public function saveChangeRequest()
    {
        $structuredId = (string)$this->request->post('id', '');
        $revisionNote = (string)$this->request->post('revision_note', '');
        try {
            QmsDocumentStructureService::saveChangeRequest(
                $structuredId,
                $revisionNote,
                (array)$this->request->post('manual_tasks', [])
            );
            Session::flash('success', '变更申请已保存，影响分析和人工待办已归档。');
        } catch (\Throwable $exception) {
            Session::flash('error', $exception->getMessage());
        }

        return redirect('/planning/structures/change-impact?id=' . $structuredId . '&revision_note=' . rawurlencode($revisionNote));
    }

    public function updateChangeRequest()
    {
        $structuredId = (string)$this->request->post('structured_document_id', '');
        $revisionNote = (string)$this->request->post('revision_note', '');
        try {
            QmsDocumentStructureService::updateChangeRequestTasks(
                (string)$this->request->post('change_request_id', ''),
                (array)$this->request->post('manual_tasks', []),
                (string)$this->request->post('close_request', '') === '1'
            );
            Session::flash('success', '变更申请待办状态已更新。');
        } catch (\Throwable $exception) {
            Session::flash('error', $exception->getMessage());
        }

        return redirect('/planning/structures/change-impact?id=' . $structuredId . '&revision_note=' . rawurlencode($revisionNote));
    }

    public function renderPackage()
    {
        $summary = QmsDocumentStructureService::renderSystemPackage();
        Session::flash('success', '体系文件组合包已生成：'
            . (int)$summary['total_documents'] . ' 份结构化文档，输出 ' . (string)$summary['output_path'] . '。');

        return redirect('/planning/structures/package');
    }

    public function seed()
    {
        $summary = QmsDocumentStructureService::seedAll();
        Session::flash('success', '文件结构化骨架已生成：原始文件 ' . (int)$summary['assets']
            . '，结构化文档 ' . (int)$summary['structured_documents']
            . '，内容块 ' . (int)$summary['blocks']
            . '，块级追溯 ' . (int)$summary['links']
            . '，渲染输出 ' . (int)$summary['rendered'] . '。');

        return redirect('/planning/structures');
    }
}
