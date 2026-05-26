<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Department;
use app\model\Document;
use app\model\QmsClause;
use app\model\QmsClauseText;
use app\model\QmsDocumentSection;
use app\model\QmsElementClauseMapping;
use app\model\QmsImportBatch;
use app\model\QmsImportCandidate;
use app\model\QmsPosition;
use app\model\QmsQualityObjective;
use app\model\QmsQualityPolicy;
use app\model\QmsRequirementElement;
use app\model\QmsResponsibilityMatrix;
use app\model\QmsSource;
use app\model\QmsTraceLink;
use app\service\FileService;
use app\service\QmsPlanningImportService;
use think\exception\HttpException;
use think\facade\Db;
use think\facade\Session;
use think\facade\View;
use think\Paginator;

class Planning extends BaseController
{
    public function sources()
    {
        $items = QmsSource::where('soft_delete', 0)
            ->where('status', 'published')
            ->order('source_code', 'asc')
            ->paginate(20);
        $candidates = QmsSource::where('soft_delete', 0)
            ->where('status', 'pending_review')
            ->order('created', 'desc')
            ->select();
        $this->decorateSources($items);
        $this->decorateSources($candidates);

        View::assign('items', $items);
        View::assign('pages', $items->render());
        View::assign('candidates', $candidates);
        View::assign('sourceTypes', $this->sourceTypeOptions());
        View::assign('manifest', QmsPlanningImportService::officialSourceManifest());

        return View::fetch('planning/sources');
    }

    public function seedSources()
    {
        $summary = ['created' => 0, 'updated' => 0, 'missing' => 0];
        foreach (QmsPlanningImportService::officialSourceManifest() as $entry) {
            if (!is_file($entry['absolute_path'])) {
                $summary['missing']++;
                continue;
            }

            $source = QmsSource::where('source_code', $entry['source_code'])
                ->where('soft_delete', 0)
                ->find();
            $isNew = !$source;
            if (!$source) {
                $source = new QmsSource();
                $source->id = qms_uuid();
            }

            $source->save([
                'source_code' => $entry['source_code'],
                'name' => $entry['name'],
                'source_type' => $entry['source_type'],
                'version' => $entry['version'],
                'effective_date' => $entry['effective_date'],
                'attachment_file_path' => $entry['relative_path'],
                'attachment_file_name' => $entry['file_name'],
                'status' => 'published',
                'publish' => 1,
                'soft_delete' => 0,
            ]);
            $summary[$isNew ? 'created' : 'updated']++;
        }

        Session::flash('success', '内置正式依据维护完成：新增 ' . $summary['created'] . '，更新 ' . $summary['updated'] . '，缺失 ' . $summary['missing']);

        return redirect('/planning/sources');
    }

    public function createSourceCandidate()
    {
        if (!$this->request->isPost()) {
            return redirect('/planning/sources');
        }

        $sourceCode = trim((string)$this->request->post('source_code', ''));
        $name = trim((string)$this->request->post('name', ''));
        if ($sourceCode === '' || $name === '') {
            Session::flash('error', '依据编号和正式名称不能为空。');

            return redirect('/planning/sources');
        }

        $published = QmsSource::where('source_code', $sourceCode)
            ->where('status', 'published')
            ->where('soft_delete', 0)
            ->find();
        if ($published) {
            Session::flash('warning', '该编号已在当前正式依据中，若版本变更请先废止旧版再登记候选。');

            return redirect('/planning/sources');
        }

        $source = QmsSource::where('source_code', $sourceCode)
            ->where('soft_delete', 0)
            ->find();
        if (!$source) {
            $source = new QmsSource();
            $source->id = qms_uuid();
        }

        $source->save([
            'source_code' => $sourceCode,
            'name' => $name,
            'source_type' => trim((string)$this->request->post('source_type', 'external_standard')) ?: 'external_standard',
            'version' => trim((string)$this->request->post('version', '')),
            'effective_date' => $this->request->post('effective_date') ?: null,
            'attachment_file_path' => trim((string)$this->request->post('attachment_file_path', '')),
            'attachment_file_name' => trim((string)$this->request->post('attachment_file_name', '')),
            'status' => 'pending_review',
            'publish' => 0,
            'soft_delete' => 0,
            'review_note' => $this->composeSourceReviewNote(),
        ]);

        Session::flash('success', '外部依据候选已登记，待质量负责人复核发布。');

        return redirect('/planning/sources');
    }

    public function uploadSourceCandidate()
    {
        if (!$this->request->isPost()) {
            return redirect('/planning/sources');
        }

        if (empty($_FILES['source_file']['name'])) {
            Session::flash('error', '请选择要导入的依据文件。');

            return redirect('/planning/sources');
        }

        $originalName = (string)$_FILES['source_file']['name'];
        $ext = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'doc', 'docx'], true)) {
            Session::flash('error', '外部依据导入仅支持 PDF、DOC、DOCX 文件。');

            return redirect('/planning/sources');
        }

        $parsed = QmsPlanningImportService::parseSourceFilename($originalName);
        $sourceCode = trim((string)($parsed['source_code'] ?? ''));
        if ($sourceCode === '') {
            $sourceCode = '待确认-' . date('YmdHis');
        }
        if (QmsSource::where('source_code', $sourceCode)->where('status', 'published')->where('soft_delete', 0)->find()) {
            Session::flash('warning', '该文件名识别出的编号已在当前正式依据中，请确认是否为新版后再登记。');

            return redirect('/planning/sources');
        }

        $source = QmsSource::where('source_code', $sourceCode)
            ->where('soft_delete', 0)
            ->find();
        if (!$source) {
            $source = new QmsSource();
            $source->id = qms_uuid();
        }

        $upload = FileService::upload($_FILES['source_file'], 'qms-sources', (string)$source->id);
        if (!$upload) {
            Session::flash('error', '文件上传失败，请确认格式和大小限制。');

            return redirect('/planning/sources');
        }

        $source->save([
            'source_code' => $sourceCode,
            'name' => trim((string)($parsed['name'] ?? '')) ?: pathinfo($originalName, PATHINFO_FILENAME),
            'source_type' => trim((string)($parsed['source_type'] ?? 'external_standard')) ?: 'external_standard',
            'version' => trim((string)($parsed['version'] ?? '')),
            'effective_date' => null,
            'attachment_file_path' => $upload['file_path'],
            'attachment_file_name' => $upload['file_name'],
            'status' => 'pending_review',
            'publish' => 0,
            'soft_delete' => 0,
            'review_note' => implode("\n", array_filter([
                '上传文件：' . $originalName,
                '自动预填：编号、名称、版本来自文件名，发布前需人工复核。',
                str_starts_with($sourceCode, '待确认-') ? '编号未能可靠识别，请人工修正后再发布。' : '',
            ])),
        ]);

        Session::flash('success', '依据文件已上传并生成待复核候选。');

        return redirect('/planning/sources');
    }

    public function publishSourceCandidate()
    {
        if (!$this->request->isPost()) {
            return redirect('/planning/sources');
        }

        $source = $this->findSource();
        if ((string)$source->status !== 'pending_review') {
            Session::flash('warning', '只有待复核依据候选可以发布。');

            return redirect('/planning/sources');
        }

        $published = QmsSource::where('source_code', (string)$source->source_code)
            ->where('status', 'published')
            ->where('soft_delete', 0)
            ->where('id', '<>', (string)$source->id)
            ->find();
        if ($published) {
            Session::flash('warning', '该编号已有正式发布记录，请先处理旧版正式依据。');

            return redirect('/planning/sources');
        }

        $source->save([
            'status' => 'published',
            'publish' => 1,
        ]);
        Session::flash('success', '外部依据已复核发布并进入当前正式依据。');

        return redirect('/planning/sources');
    }

    public function checkSourceCandidate()
    {
        if (!$this->request->isPost()) {
            return redirect('/planning/sources');
        }

        $source = $this->findSource();
        if ((string)$source->status !== 'pending_review') {
            Session::flash('warning', '只有待复核依据候选可以执行查新和补齐。');

            return redirect('/planning/sources');
        }

        $sourceCode = trim((string)$this->request->post('source_code', ''));
        $name = trim((string)$this->request->post('name', ''));
        if ($sourceCode === '' || $name === '') {
            Session::flash('error', '查新补齐时，依据编号和正式名称不能为空。');

            return redirect('/planning/sources');
        }

        $duplicate = QmsSource::where('source_code', $sourceCode)
            ->where('soft_delete', 0)
            ->where('id', '<>', (string)$source->id)
            ->find();
        if ($duplicate) {
            Session::flash('warning', '该编号已存在，请确认是否为同一依据或新版文件。');

            return redirect('/planning/sources');
        }

        $source->save([
            'source_code' => $sourceCode,
            'name' => $name,
            'source_type' => trim((string)$this->request->post('source_type', 'external_standard')) ?: 'external_standard',
            'version' => trim((string)$this->request->post('version', '')),
            'effective_date' => $this->request->post('effective_date') ?: null,
            'attachment_file_path' => trim((string)$this->request->post('attachment_file_path', '')),
            'attachment_file_name' => trim((string)$this->request->post('attachment_file_name', '')),
            'review_note' => $this->composeSourceReviewNote(),
        ]);
        Session::flash('success', '查新信息已记录，候选依据已补齐。');

        return redirect('/planning/sources');
    }

    public function createSourceClauseCandidates()
    {
        if (!$this->request->isPost()) {
            return redirect('/planning/sources');
        }

        $source = $this->findSource();
        $sourceArray = method_exists($source, 'toArray') ? $source->toArray() : [
            'id' => (string)$source->id,
            'source_code' => (string)$source->source_code,
            'name' => (string)$source->name,
            'status' => (string)$source->status,
            'attachment_file_path' => (string)$source->attachment_file_path,
            'attachment_file_name' => (string)$source->attachment_file_name,
        ];
        $sourceCode = (string)$source->source_code;
        $candidates = QmsPlanningImportService::buildRegisteredSourceClauseCandidates($sourceArray);
        $publishedTitles = $this->publishedClauseTitlesByNumber($sourceCode);
        $candidates = QmsPlanningImportService::applyPublishedReviewTitleBaseline($candidates, $publishedTitles, $sourceCode);
        if ($candidates === []) {
            Session::flash('warning', '未能从该依据文件识别条款结构。请确认文件可读取，或后续改用人工结构化补录。');

            return redirect('/planning/sources');
        }

        $extractionMethod = (string)($candidates[0]['payload']['extraction_method'] ?? '');
        $hasModelSummaries = count(array_filter(
            $candidates,
            fn (array $candidate): bool => (string)($candidate['payload']['title_source'] ?? '') === 'model_summarized'
        )) > 0;
        $hasPublishedBaseline = count(array_filter(
            $candidates,
            fn (array $candidate): bool => (string)($candidate['payload']['title_source'] ?? '') === 'published_review_baseline'
        )) > 0;
        $batch = $this->replaceCandidateBatch(
            '条款结构化候选：' . $sourceCode,
            'source_clauses',
            $candidates,
            '来源依据：' . $sourceCode . ' ' . (string)$source->name . '；'
                . ($hasPublishedBaseline
                    ? '标题已优先沿用同一依据已发布复核条款；逐字原文仍来自附件自动抽取，需复核确认。'
                    : ($hasModelSummaries
                    ? '首批依据采用 model_summarized 模型短标题；逐字原文仍来自附件自动抽取，并已写入检查提示。'
                    : '候选发布前会校验该依据是否已进入当前正式依据。')),
            $sourceCode
        );
        Session::flash('success', ($hasModelSummaries ? '已生成首批模型标题条款候选：' : '已从具体依据文件生成条款候选：') . count($candidates) . ' 条，请在导入复核中逐条复核、修正、发布或退回。');

        return redirect('/planning/import-batches?batch_id=' . $batch->id);
    }

    public function obsoleteSource()
    {
        if (!$this->request->isPost()) {
            return redirect('/planning/sources');
        }

        $source = $this->findSource();
        $source->save([
            'status' => 'obsolete',
            'publish' => 0,
        ]);
        Session::flash('success', '外部依据已标记废止，历史记录保留。');

        return redirect('/planning/sources');
    }

    public function clauses()
    {
        $query = QmsClause::where('soft_delete', 0);
        $sourceId = trim((string)$this->request->param('source_id', ''));
        $clauseNumber = trim((string)$this->request->param('clause_number', ''));
        $keyword = trim((string)$this->request->param('keyword', ''));
        $reviewStatus = trim((string)$this->request->param('review_status', ''));
        $applicability = trim((string)$this->request->param('applicability', ''));

        if ($sourceId !== '') {
            $query->where('source_id', $sourceId);
        }
        if ($clauseNumber !== '') {
            $query->where('clause_number', 'like', '%' . $clauseNumber . '%');
        }
        if ($reviewStatus !== '') {
            $query->where('review_status', $reviewStatus);
        }
        if ($applicability !== '') {
            $query->where('applicability', $applicability);
        }
        if ($keyword !== '') {
            $textClauseIds = QmsClauseText::where('soft_delete', 0)
                ->where(function ($q) use ($keyword) {
                    $q->where('original_text', 'like', '%' . $keyword . '%')
                        ->whereOr('review_note', 'like', '%' . $keyword . '%');
                })
                ->column('clause_id');
            $query->where(function ($q) use ($keyword, $textClauseIds) {
                $q->where('title', 'like', '%' . $keyword . '%');
                if (!empty($textClauseIds)) {
                    $q->whereOr('id', 'in', $textClauseIds);
                }
            });
        }

        $sources = QmsSource::where('soft_delete', 0)
            ->where('status', 'published')
            ->order('source_code', 'asc')
            ->select();
        $sourceMap = $this->sourceMap();
        $allItems = $query->select();
        $clauseMeta = $this->clauseHierarchyMeta($allItems);
        $allItemArray = is_array($allItems) ? $allItems : iterator_to_array($allItems);
        usort($allItemArray, function ($left, $right) use ($sourceMap, $clauseMeta): int {
            $leftSource = $sourceMap[(string)$left->source_id] ?? (string)$left->source_id;
            $rightSource = $sourceMap[(string)$right->source_id] ?? (string)$right->source_id;
            $sourceCompare = strcmp($leftSource, $rightSource);
            if ($sourceCompare !== 0) {
                return $sourceCompare;
            }

            return strcmp(
                $this->clauseDisplaySortKey((string)$left->id, $clauseMeta),
                $this->clauseDisplaySortKey((string)$right->id, $clauseMeta)
            );
        });
        $listRows = 30;
        $currentPage = max(1, (int)$this->request->param('page', 1));
        $total = count($allItemArray);
        $pageItems = array_slice($allItemArray, ($currentPage - 1) * $listRows, $listRows);
        $items = Paginator::make($pageItems, $listRows, $currentPage, $total, false, [
            'path' => '/planning/clauses',
            'query' => $this->request->get(),
        ]);
        $clauseTextMap = $this->clauseTextMap($items);
        foreach ($items as $item) {
            $item->setAttr('source_code', $sourceMap[(string)$item->source_id] ?? (string)$item->source_id);
            $item->setAttr('clause_tree_indent', $this->clauseTreeIndent((string)$item->id, $clauseMeta));
            $item->setAttr('clause_hierarchy_label', $this->clauseHierarchyLabel((string)$item->id, $clauseMeta));
            $text = $clauseTextMap[(string)$item->id] ?? null;
            $item->setAttr('clause_original_text', $text ? (string)$text->original_text : '');
            $item->setAttr('clause_review_note', $text ? (string)$text->review_note : '');
            $item->setAttr('clause_text_locator', $text ? (string)($text->locator ?: $item->locator) : (string)$item->locator);
            $item->setAttr('clause_text_method', $text ? (string)$text->extraction_method : '-');
            $item->setAttr('clause_text_status_label', $text ? $this->clauseStatusLabel((string)$text->review_status) : '未抽取');
            $item->setAttr('review_status_label', $this->clauseStatusLabel((string)$item->review_status));
            $item->setAttr('applicability_label', $this->applicabilityLabel((string)$item->applicability));
        }

        View::assign('items', $items);
        View::assign('pages', $items->render());
        View::assign('sources', $sources);
        View::assign('sourceMap', $sourceMap);
        View::assign('filter', [
            'source_id' => $sourceId,
            'clause_number' => $clauseNumber,
            'keyword' => $keyword,
            'review_status' => $reviewStatus,
            'applicability' => $applicability,
        ]);

        return View::fetch('planning/clauses');
    }

    private function clauseHierarchyMeta(iterable $items): array
    {
        $sourceIds = [];
        foreach ($items as $item) {
            $sourceIds[(string)$item->source_id] = true;
        }
        if ($sourceIds === []) {
            return [];
        }

        $rows = QmsClause::where('soft_delete', 0)
            ->whereIn('source_id', array_keys($sourceIds))
            ->field('id,parent_id,clause_number,title,level')
            ->select();
        $meta = [];
        foreach ($rows as $row) {
            $meta[(string)$row->id] = [
                'parent_id' => (string)($row->parent_id ?? ''),
                'clause_number' => (string)$row->clause_number,
                'title' => (string)$row->title,
                'level' => (int)$row->level,
            ];
        }

        return $meta;
    }

    private function clauseDisplaySortKey(string $clauseId, array $meta): string
    {
        $segments = [];
        $currentId = $clauseId;
        $seen = [];
        while ($currentId !== '' && isset($meta[$currentId]) && !isset($seen[$currentId])) {
            $seen[$currentId] = true;
            array_unshift($segments, QmsPlanningImportService::clauseDisplaySortToken((string)$meta[$currentId]['clause_number']));
            $currentId = (string)($meta[$currentId]['parent_id'] ?? '');
        }

        return implode('|', $segments);
    }

    private function clauseTreeIndent(string $clauseId, array $meta): int
    {
        $depth = 0;
        $currentId = (string)($meta[$clauseId]['parent_id'] ?? '');
        $seen = [];
        while ($currentId !== '' && isset($meta[$currentId]) && !isset($seen[$currentId])) {
            $seen[$currentId] = true;
            $depth++;
            $currentId = (string)($meta[$currentId]['parent_id'] ?? '');
        }

        return min($depth, 6);
    }

    private function clauseHierarchyLabel(string $clauseId, array $meta): string
    {
        $labels = [];
        $currentId = (string)($meta[$clauseId]['parent_id'] ?? '');
        $seen = [];
        while ($currentId !== '' && isset($meta[$currentId]) && !isset($seen[$currentId])) {
            $seen[$currentId] = true;
            array_unshift($labels, (string)$meta[$currentId]['clause_number'] . ' ' . (string)$meta[$currentId]['title']);
            $currentId = (string)($meta[$currentId]['parent_id'] ?? '');
        }

        return implode(' / ', $labels);
    }

    public function positions()
    {
        $items = QmsPosition::where('soft_delete', 0)
            ->order('code', 'asc')
            ->paginate(30);

        View::assign('items', $items);
        View::assign('pages', $items->render());

        return View::fetch('planning/positions');
    }

    public function elements()
    {
        $items = QmsRequirementElement::where('soft_delete', 0)
            ->order('element_code', 'asc')
            ->paginate(40);

        foreach ($items as $item) {
            $elementCode = (string)$item->element_code;
            $sourceMappings = QmsElementClauseMapping::where('soft_delete', 0)
                ->where('element_code', $elementCode)
                ->where('review_status', 'published')
                ->count();
            $supplementMappings = QmsElementClauseMapping::where('soft_delete', 0)
                ->where('element_code', $elementCode)
                ->where('mapping_basis', 'supplement')
                ->where('review_status', 'published')
                ->count();
            $chain = $this->traceabilityChainCounts($elementCode);
            $responsibilities = QmsResponsibilityMatrix::where('soft_delete', 0)
                ->where('clause_number', $elementCode)
                ->where('review_status', 'published')
                ->count();

            $item->setAttr('source_mapping_count', $sourceMappings);
            $item->setAttr('source_supplement_count', $supplementMappings);
            $item->setAttr('responsibility_count', $responsibilities);
            $item->setAttr('trace_status_label', $this->traceStatusLabel($chain));
            $item->setAttr('trace_status_class', $chain['manual_sections'] > 0 && $chain['documents'] > 0 ? 'success' : ($chain['manual_sections'] > 0 ? 'warning text-dark' : 'secondary'));
        }

        View::assign('items', $items);
        View::assign('pages', $items->render());

        return View::fetch('planning/elements');
    }

    public function responsibilityMatrix()
    {
        $items = QmsResponsibilityMatrix::where('soft_delete', 0)
            ->order('clause_number', 'asc')
            ->order('position_name', 'asc')
            ->paginate(40);

        View::assign('items', $items);
        View::assign('pages', $items->render());

        return View::fetch('planning/responsibility_matrix');
    }

    public function objectives()
    {
        $objectives = QmsQualityObjective::where('soft_delete', 0)->order('year', 'desc')->order('title', 'asc')->paginate(30);
        View::assign('policies', QmsQualityPolicy::where('soft_delete', 0)->order('effective_date', 'desc')->select());
        View::assign('objectives', $objectives);
        View::assign('objectivePages', $objectives->render());
        View::assign('departments', Department::where('soft_delete', 0)->select());
        View::assign('positions', QmsPosition::where('soft_delete', 0)->select());

        return View::fetch('planning/objectives');
    }

    public function createPolicy()
    {
        if (!$this->request->isPost()) {
            return redirect('/planning/objectives');
        }

        if ((int)$this->request->post('is_current', 0) === 1) {
            QmsQualityPolicy::where('soft_delete', 0)->update(['is_current' => 0]);
        }

        $policy = new QmsQualityPolicy();
        $policy->id = qms_uuid();
        $policy->save([
            'title' => trim((string)$this->request->post('title', '质量方针')),
            'policy_text' => trim((string)$this->request->post('policy_text', '')),
            'version' => trim((string)$this->request->post('version', '')),
            'effective_date' => $this->request->post('effective_date') ?: null,
            'is_current' => (int)$this->request->post('is_current', 0),
            'management_review_input' => (int)$this->request->post('management_review_input', 1),
            'review_status' => $this->request->post('review_status', 'draft'),
        ]);

        Session::flash('success', '质量方针已登记。');

        return redirect('/planning/objectives');
    }

    public function createObjective()
    {
        if (!$this->request->isPost()) {
            return redirect('/planning/objectives');
        }

        $objective = new QmsQualityObjective();
        $objective->id = qms_uuid();
        $objective->save([
            'year' => (int)$this->request->post('year', date('Y')),
            'department_id' => $this->request->post('department_id') ?: null,
            'position_id' => $this->request->post('position_id') ?: null,
            'title' => trim((string)$this->request->post('title', '')),
            'metric_name' => trim((string)$this->request->post('metric_name', '')),
            'target_value' => trim((string)$this->request->post('target_value', '')),
            'unit' => trim((string)$this->request->post('unit', '')),
            'statistic_cycle' => $this->request->post('statistic_cycle', 'annual'),
            'responsible_department' => trim((string)$this->request->post('responsible_department', '')),
            'responsible_position' => trim((string)$this->request->post('responsible_position', '')),
            'management_review_input' => (int)$this->request->post('management_review_input', 1),
            'review_status' => $this->request->post('review_status', 'draft'),
        ]);

        Session::flash('success', '质量目标已登记。');

        return redirect('/planning/objectives');
    }

    public function documentSections()
    {
        $items = QmsDocumentSection::where('soft_delete', 0)
            ->order('section_number', 'asc')
            ->paginate(40);
        $documentMap = $this->documentMap();
        $sectionMap = [];
        foreach (QmsDocumentSection::where('soft_delete', 0)->field('section_number,title')->select() as $section) {
            $sectionMap[(string)$section->section_number] = (string)$section->title;
        }
        foreach ($items as $item) {
            $item->setAttr('document_title', $documentMap[(string)$item->document_id] ?? '待关联');
            $item->setAttr('section_hierarchy_label', $this->sectionHierarchyLabel((string)$item->section_number, $sectionMap));
            $item->setAttr('clause_tree_indent', max(0, substr_count((string)$item->section_number, '.') * 18));
        }

        View::assign('items', $items);
        View::assign('pages', $items->render());
        View::assign('documentMap', $documentMap);

        return View::fetch('planning/document_sections');
    }

    public function traceability()
    {
        $elements = QmsRequirementElement::where('soft_delete', 0)
            ->where('review_status', 'published')
            ->order('element_code', 'asc')
            ->limit(300)
            ->select();

        $rows = [];
        foreach ($elements as $element) {
            $chain = $this->traceabilityChainCounts((string)$element->element_code);
            $rows[] = [
                'element' => $element,
                'traceability_chain' => '外部条款 → 体系要素 → 质量手册章节 → 程序文件 → 记录表格 → 运行模块',
                'source_mappings' => QmsElementClauseMapping::where('soft_delete', 0)
                    ->where('element_code', (string)$element->element_code)
                    ->where('review_status', 'published')
                    ->count(),
                'manual_sections' => $chain['manual_sections'],
                'documents' => $chain['documents'],
                'program_documents' => $chain['documents'],
                'record_forms' => $chain['record_forms'],
                'business_modules' => $chain['business_modules'],
                'objectives' => $this->countTraceTargets('requirement_element', (string)$element->element_code, 'quality_objective'),
                'positions' => QmsResponsibilityMatrix::where('soft_delete', 0)
                    ->where('clause_number', (string)$element->element_code)
                    ->where('review_status', 'published')
                    ->count(),
            ];
        }

        View::assign('rows', $rows);

        return View::fetch('planning/traceability');
    }

    public function importBatches()
    {
        $batchType = trim((string)$this->request->param('batch_type', ''));
        $batchQuery = QmsImportBatch::where('soft_delete', 0);
        if ($batchType !== '') {
            $batchQuery->where('batch_type', $batchType);
        }
        $batches = $batchQuery->order('created', 'desc')
            ->paginate([
                'list_rows' => 20,
                'query' => [
                    'batch_id' => $this->request->param('batch_id', ''),
                    'batch_type' => $batchType,
                ],
            ]);
        $batchId = trim((string)$this->request->param('batch_id', ''));
        $candidates = [];
        $selectedBatch = null;
        $candidatePages = '';
        foreach ($batches as $batch) {
            $batch->setAttr('batch_type_label', $this->batchTypeLabel((string)$batch->batch_type));
            $batch->setAttr('status_label', $this->statusLabel((string)$batch->status));
        }
        if ($batchId !== '') {
            $selectedBatch = QmsImportBatch::where('soft_delete', 0)
                ->find($batchId);
            if ($selectedBatch) {
                $selectedBatch->setAttr('batch_type_label', $this->batchTypeLabel((string)$selectedBatch->batch_type));
                $selectedBatch->setAttr('status_label', $this->statusLabel((string)$selectedBatch->status));
            }
            if ($selectedBatch) {
                $candidates = QmsImportCandidate::where('soft_delete', 0)
                    ->where('batch_id', $batchId)
                    ->order('candidate_type', 'asc')
                    ->order('source_locator', 'asc')
                    ->order('created', 'asc')
                    ->paginate([
                        'list_rows' => 50,
                        'var_page' => 'candidate_page',
                        'query' => ['batch_id' => $batchId, 'batch_type' => $batchType],
                    ]);
                $this->decorateCandidates($candidates);
                $candidatePages = $candidates->render();
            }
        }

        View::assign('batches', $batches);
        View::assign('pages', $batches->render());
        View::assign('candidates', $candidates);
        View::assign('batchId', $batchId);
        View::assign('batchType', $batchType);
        View::assign('batchTypeOptions', $this->batchTypeOptions());
        View::assign('selectedBatch', $selectedBatch);
        View::assign('candidatePages', $candidatePages);

        return View::fetch('planning/import_batches');
    }

    public function createClauseCandidates()
    {
        $candidates = QmsPlanningImportService::buildExternalClauseCandidates();
        $batch = $this->createCandidateBatch('外部依据条款候选', 'external_clauses', $candidates);
        Session::flash('success', '外部依据条款候选已生成：' . count($candidates) . ' 条');

        return redirect('/planning/import-batches?batch_id=' . $batch->id);
    }

    public function createManualCandidates()
    {
        $candidates = QmsPlanningImportService::buildCurrentManualCandidates();
        $batch = $this->replaceCandidateBatch(
            '现用质量手册附录候选',
            'current_manual',
            $candidates,
            '以现用质量手册附录16为准，参考2025质量手册补充程序文件候选；重新生成会覆盖旧候选。',
            'current_manual_appendix16'
        );
        Session::flash('success', '现用质量手册候选已生成：' . count($candidates) . ' 条');

        return redirect('/planning/import-batches?batch_type=current_manual&batch_id=' . $batch->id);
    }

    public function syncInternalDocuments()
    {
        if (!$this->request->isPost()) {
            return redirect('/planning/elements');
        }

        $documents = QmsPlanningImportService::buildInternalDocumentBaselines();
        $summary = ['created' => 0, 'updated' => 0, 'missing' => 0];
        Db::transaction(function () use ($documents, &$summary): void {
            $manualId = '';
            foreach ($documents as $entry) {
                if (($entry['match_confidence'] ?? '') === 'missing_file') {
                    $summary['missing']++;
                    continue;
                }

                $document = Document::where('doc_number', (string)$entry['doc_number'])
                    ->where('soft_delete', 0)
                    ->find();
                $isNew = !$document;
                if (!$document) {
                    $document = new Document();
                    $document->id = qms_uuid();
                }

                $document->save([
                    'level' => (int)$entry['document_level'],
                    'doc_number' => (string)$entry['doc_number'],
                    'title' => (string)$entry['title'],
                    'version' => (string)$entry['version'],
                    'status' => 'published',
                    'file_path' => (string)$entry['file_path'],
                    'file_name' => (string)$entry['file_name'],
                    'file_type' => (string)$entry['file_type'],
                    'change_reason' => (string)($entry['source_note'] ?? '体系策划中心自动登记内部文件基线。'),
                    'publish' => 1,
                    'soft_delete' => 0,
                ]);
                $summary[$isNew ? 'created' : 'updated']++;
                if ((int)$entry['document_level'] === 1) {
                    $manualId = (string)$document->id;
                }
            }

            if ($manualId !== '') {
                QmsDocumentSection::where('soft_delete', 0)
                    ->where(function ($query) {
                        $query->whereNull('document_id')->whereOr('document_id', '');
                    })
                    ->update(['document_id' => $manualId]);
            }
        });

        Session::flash('success', '内部文件基线登记完成：新增 ' . $summary['created'] . '，更新 ' . $summary['updated'] . '，缺失 ' . $summary['missing'] . '。');

        return redirect('/planning/elements');
    }

    public function createTraceabilitySample()
    {
        $candidates = QmsPlanningImportService::buildTrainingTraceCandidates();
        $batch = $this->createCandidateBatch('6.2 人员培训追溯样板候选', 'sample_6_2', $candidates);
        Session::flash('success', '6.2 人员培训样板候选已生成：' . count($candidates) . ' 条');

        return redirect('/planning/import-batches?batch_id=' . $batch->id);
    }

    public function publishCandidate()
    {
        $candidate = $this->findCandidate();
        if ((string)$candidate->status === 'published') {
            Session::flash('warning', '该候选项已发布。');

            return redirect('/planning/import-batches?batch_id=' . $candidate->batch_id);
        }

        $payload = json_decode((string)$candidate->payload, true);
        if (!is_array($payload)) {
            throw new HttpException(400, '候选项载荷格式无效');
        }

        $this->publishCandidatePayload($candidate, $payload);
        $this->refreshBatchCounts((string)$candidate->batch_id);
        Session::flash('success', '候选项已复核发布。');

        return redirect('/planning/import-batches?batch_id=' . $candidate->batch_id);
    }

    public function publishCandidateBatch()
    {
        $batchId = trim((string)$this->request->param('batch_id', ''));
        if ($batchId === '') {
            throw new HttpException(400, '缺少候选批次。');
        }

        $batch = QmsImportBatch::where('soft_delete', 0)->find($batchId);
        if (!$batch) {
            throw new HttpException(404, '候选批次不存在。');
        }

        $candidates = QmsImportCandidate::where('soft_delete', 0)
            ->where('batch_id', $batchId)
            ->where('status', 'pending_review')
            ->order('candidate_type', 'asc')
            ->order('id', 'asc')
            ->select();
        if (count($candidates) === 0) {
            Session::flash('warning', '当前批次没有待复核候选可发布。');

            return redirect('/planning/import-batches?batch_id=' . $batchId);
        }

        $publishedCount = 0;
        try {
            Db::transaction(function () use ($candidates, &$publishedCount): void {
                foreach ($candidates as $candidate) {
                    $payload = json_decode((string)$candidate->payload, true);
                    if (!is_array($payload)) {
                        throw new HttpException(400, '候选项载荷格式无效：' . (string)$candidate->id);
                    }
                    $this->publishCandidatePayload($candidate, $payload);
                    $publishedCount++;
                }
            });
        } catch (\Throwable $exception) {
            Session::flash('error', '批量发布失败，已回滚本次发布：' . $exception->getMessage());

            return redirect('/planning/import-batches?batch_id=' . $batchId);
        }

        $this->refreshBatchCounts($batchId);
        Session::flash('success', '已批量发布待复核候选：' . $publishedCount . ' 条。');

        return redirect('/planning/import-batches?batch_id=' . $batchId);
    }

    public function rejectCandidate()
    {
        $candidate = $this->findCandidate();
        $candidate->save([
            'status' => 'rejected',
            'review_note' => trim((string)$this->request->post('review_note', '')),
        ]);
        $this->refreshBatchCounts((string)$candidate->batch_id);
        Session::flash('success', '候选项已退回。');

        return redirect('/planning/import-batches?batch_id=' . $candidate->batch_id);
    }

    public function obsoleteCandidate()
    {
        $candidate = $this->findCandidate();
        $candidate->save(['status' => 'obsolete']);
        $this->refreshBatchCounts((string)$candidate->batch_id);
        Session::flash('success', '候选项已废止。');

        return redirect('/planning/import-batches?batch_id=' . $candidate->batch_id);
    }

    public function updateClauseCandidate()
    {
        $candidate = $this->findCandidate();
        if ((string)$candidate->candidate_type !== 'clause') {
            throw new HttpException(400, '当前仅支持修正条款候选。');
        }
        if ((string)$candidate->status === 'published') {
            throw new HttpException(400, '已发布候选不能直接修正，请在正式条款库中按变更流程处理。');
        }

        $payload = json_decode((string)$candidate->payload, true);
        if (!is_array($payload)) {
            throw new HttpException(400, '候选项载荷格式无效');
        }

        $title = trim((string)$this->request->post('title', ''));
        $originalText = trim((string)$this->request->post('original_text', ''));
        $locator = trim((string)$this->request->post('locator', ''));
        if ($title === '' || $originalText === '') {
            throw new HttpException(400, '条款标题和逐字原文不能为空。');
        }

        $payload['title'] = $title;
        $payload['original_text'] = $originalText;
        $payload['locator'] = $locator !== '' ? $locator : (string)($payload['locator'] ?? '');
        $payload['title_source'] = 'manual';
        $payload['review_status'] = 'pending_review';
        $payload['manual_review_note'] = trim((string)$this->request->post('review_note', ''));

        $candidate->save([
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
            'status' => 'pending_review',
            'review_note' => $payload['manual_review_note'],
        ]);
        $this->refreshBatchCounts((string)$candidate->batch_id);
        Session::flash('success', '条款候选已修正，发布前请再次核对逐字原文。');

        return redirect('/planning/import-batches?batch_id=' . $candidate->batch_id);
    }

    private function createCandidateBatch(string $name, string $type, array $candidates, string $note = ''): QmsImportBatch
    {
        return $this->saveCandidateBatch($name, $type, $candidates, $note);
    }

    private function replaceCandidateBatch(string $name, string $type, array $candidates, string $note, string $sourceCode): QmsImportBatch
    {
        if ($type === 'source_clauses' && $sourceCode !== '') {
            $this->replaceSourceClauseBatches($sourceCode);
        } elseif ($sourceCode !== '') {
            $this->replaceCandidateBatches($type, 'source:' . $sourceCode);
        }

        return $this->saveCandidateBatch($name, $type, $candidates, $note, $sourceCode !== '' ? 'source:' . $sourceCode : '');
    }

    private function publishedClauseTitlesByNumber(string $sourceCode): array
    {
        if ($sourceCode === '') {
            return [];
        }

        $source = QmsSource::where('source_code', $sourceCode)
            ->where('soft_delete', 0)
            ->find();
        if (!$source) {
            return [];
        }

        $rows = QmsClause::where('source_id', (string)$source->id)
            ->where('review_status', 'published')
            ->where('soft_delete', 0)
            ->field('clause_number,title')
            ->select();
        $titles = [];
        foreach ($rows as $row) {
            $titles[(string)$row->clause_number] = (string)$row->title;
        }

        return $titles;
    }

    private function replaceSourceClauseBatches(string $sourceCode): void
    {
        $sourceClauseBatchType = 'source_clauses';
        $batches = QmsImportBatch::where('soft_delete', 0)
            ->where('batch_type', $sourceClauseBatchType)
            ->where(function ($query) use ($sourceCode) {
                $query->where('source_path', 'source:' . $sourceCode)
                    ->whereOr('name', 'like', '%' . $sourceCode . '%')
                    ->whereOr('note', 'like', '%' . $sourceCode . '%');
            })
            ->select();
        $batchIds = [];
        foreach ($batches as $batch) {
            $batchIds[] = (string)$batch->id;
        }
        if ($batchIds === []) {
            return;
        }

        QmsImportCandidate::whereIn('batch_id', $batchIds)->update(['soft_delete' => 1]);
        QmsImportBatch::whereIn('id', $batchIds)->update(['soft_delete' => 1, 'status' => 'obsolete']);
    }

    private function replaceCandidateBatches(string $type, string $sourcePath): void
    {
        $batches = QmsImportBatch::where('soft_delete', 0)
            ->where('batch_type', $type)
            ->where('source_path', $sourcePath)
            ->select();
        $batchIds = [];
        foreach ($batches as $batch) {
            $batchIds[] = (string)$batch->id;
        }
        if ($batchIds === []) {
            return;
        }

        QmsImportCandidate::whereIn('batch_id', $batchIds)->update(['soft_delete' => 1]);
        QmsImportBatch::whereIn('id', $batchIds)->update(['soft_delete' => 1, 'status' => 'obsolete']);
    }

    private function saveCandidateBatch(string $name, string $type, array $candidates, string $note = '', string $sourcePath = ''): QmsImportBatch
    {
        $batch = new QmsImportBatch();
        $batch->id = qms_uuid();
        $batch->save([
            'name' => $name,
            'batch_type' => $type,
            'source_path' => $sourcePath !== '' ? $sourcePath : null,
            'status' => 'pending_review',
            'total_candidates' => count($candidates),
            'note' => $note !== '' ? $note : '候选数据需质量负责人复核发布后才进入正式追溯矩阵。',
        ]);

        foreach ($candidates as $candidate) {
            $record = new QmsImportCandidate();
            $record->id = qms_uuid();
            $payload = $candidate['payload'] ?? [];
            $record->save([
                'batch_id' => $batch->id,
                'candidate_type' => $candidate['candidate_type'] ?? 'unknown',
                'source_code' => $candidate['source_code'] ?? ($payload['source_code'] ?? null),
                'source_locator' => $payload['sort_key'] ?? $payload['locator'] ?? $payload['source'] ?? null,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
                'status' => 'pending_review',
            ]);
        }

        return $batch;
    }

    private function decorateCandidates(iterable $candidates): void
    {
        foreach ($candidates as $candidate) {
            $payload = json_decode((string)$candidate->payload, true);
            if (!is_array($payload)) {
                $candidate->setAttr('payload_summary', '候选数据格式异常，需退回检查。');
                $candidate->setAttr('payload_from', '-');
                $candidate->setAttr('payload_to', '-');
                $candidate->setAttr('payload_relation', '-');
                $candidate->setAttr('payload_note', '-');
                $candidate->setAttr('payload_original_text', '');
                $candidate->setAttr('payload_pretty', (string)$candidate->payload);
                continue;
            }

            $candidate->setAttr('payload_summary', $this->candidateSummary((string)$candidate->candidate_type, $payload));
            $candidate->setAttr('candidate_type_label', $this->candidateTypeLabel((string)$candidate->candidate_type));
            $candidate->setAttr('status_label', $this->statusLabel((string)$candidate->status));
            $endpoint = $this->candidateEndpointAttrs((string)$candidate->candidate_type, $payload);
            $candidate->setAttr('payload_from', $endpoint['from']);
            $candidate->setAttr('payload_to', $endpoint['to']);
            $candidate->setAttr('payload_relation', $endpoint['relation']);
            $candidate->setAttr('payload_note', (string)($payload['evidence_note'] ?? $payload['summary'] ?? $payload['mapping_source'] ?? $payload['source_basis'] ?? $payload['source'] ?? '-'));
            if ((string)$candidate->candidate_type === 'clause') {
                $candidate->setAttr('payload_note', $this->clauseCandidateNote($payload));
                $candidate->setAttr('payload_original_text', (string)($payload['original_text'] ?? ''));
                $candidate->setAttr('payload_title', (string)($payload['title'] ?? ''));
                $candidate->setAttr('payload_locator_value', (string)($payload['locator'] ?? ''));
                $candidate->setAttr('payload_review_note', (string)($payload['manual_review_note'] ?? $candidate->review_note ?? ''));
            } else {
                $candidate->setAttr('payload_original_text', '');
                $candidate->setAttr('payload_title', '');
                $candidate->setAttr('payload_locator_value', '');
                $candidate->setAttr('payload_review_note', '');
            }
            $candidate->setAttr('payload_pretty', (string)json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        }
    }

    private function clauseCandidateNote(array $payload): string
    {
        $titleSource = [
            'original_heading' => '原文标题',
            'auto_simplified' => '自动简化标题',
            'appendix_heading' => '附录标题',
            'known_heading' => '标准已知标题',
            'model_summarized' => '模型摘要标题',
            'published_review_baseline' => '已发布复核标题',
            'manual' => '人工标题',
        ][(string)($payload['title_source'] ?? '')] ?? (string)($payload['title_source'] ?? '-');
        $locator = (string)($payload['locator'] ?? '-');
        $rawHeading = trim((string)($payload['raw_heading'] ?? ''));
        $note = '标题来源：' . $titleSource . '；定位：' . $locator;
        if ($rawHeading !== '') {
            $note .= '；原文标题：' . $rawHeading;
        }

        return $note;
    }

    private function statusLabel(string $status): string
    {
        return [
            'candidate' => '候选',
            'draft' => '草稿',
            'pending_review' => '待复核',
            'published' => '已发布',
            'rejected' => '已退回',
            'obsolete' => '已废止',
        ][$status] ?? $status;
    }

    private function clauseStatusLabel(string $status): string
    {
        return $this->statusLabel($status);
    }

    private function applicabilityLabel(string $applicability): string
    {
        return [
            'applicable' => '适用',
            'not_applicable' => '不适用',
            'conditional' => '条件适用',
        ][$applicability] ?? ($applicability !== '' ? $applicability : '-');
    }

    private function batchTypeLabel(string $batchType): string
    {
        return $this->batchTypeOptions()[$batchType] ?? $batchType;
    }

    private function batchTypeOptions(): array
    {
        return [
            'external_clauses' => '外部依据条款',
            'source_clauses' => '依据条款结构化',
            'current_manual' => '现用手册附录',
            'sample_6_2' => '6.2 样板链',
        ];
    }

    private function candidateTypeLabel(string $candidateType): string
    {
        return [
            'clause' => '外部条款',
            'requirement_element' => '体系要素',
            'element_clause_mapping' => '要素-条款映射',
            'position' => '岗位',
            'responsibility' => '职责分配',
            'document_section' => '手册章节',
            'trace_link' => '追溯关系',
        ][$candidateType] ?? $candidateType;
    }

    private function candidateEndpointAttrs(string $candidateType, array $payload): array
    {
        if ($candidateType === 'clause') {
            return [
                'from' => '依据：' . (string)($payload['source_code'] ?? '-'),
                'to' => '条款：' . (string)($payload['clause_number'] ?? '-') . ' ' . (string)($payload['title'] ?? ''),
                'relation' => '结构化抽取',
            ];
        }

        if ($candidateType === 'requirement_element') {
            return [
                'from' => '现用质量手册附录16',
                'to' => $this->endpointLabel('requirement_element', (string)($payload['element_code'] ?? '-')),
                'relation' => '建立要素',
            ];
        }

        if ($candidateType === 'element_clause_mapping') {
            return [
                'from' => '外部依据：' . (string)($payload['source_code'] ?? '-') . ' ' . (string)($payload['clause_number'] ?? '-'),
                'to' => $this->endpointLabel('requirement_element', (string)($payload['element_code'] ?? '-')),
                'relation' => $this->relationLabel((string)($payload['mapping_basis'] ?? 'maps_to')),
            ];
        }

        return [
            'from' => $this->endpointLabel((string)($payload['source_type'] ?? $candidateType), (string)($payload['source_key'] ?? $payload['source_code'] ?? $payload['manual_section'] ?? '-')),
            'to' => $this->endpointLabel((string)($payload['target_type'] ?? ''), (string)($payload['target_title'] ?? $payload['target_key'] ?? $payload['title'] ?? $payload['name'] ?? '-')),
            'relation' => $this->relationLabel((string)($payload['relation_type'] ?? '')),
        ];
    }

    private function candidateSummary(string $candidateType, array $payload): string
    {
        if ($candidateType === 'trace_link') {
            return $this->endpointLabel((string)($payload['source_type'] ?? ''), (string)($payload['source_key'] ?? '-'))
                . ' → '
                . $this->endpointLabel((string)($payload['target_type'] ?? ''), (string)($payload['target_title'] ?? $payload['target_key'] ?? '-'));
        }

        if ($candidateType === 'clause') {
            return '条款 ' . (string)($payload['clause_number'] ?? '-') . ' ' . (string)($payload['title'] ?? '');
        }

        if ($candidateType === 'requirement_element') {
            return '体系要素 ' . (string)($payload['element_code'] ?? '-') . ' ' . (string)($payload['name'] ?? '');
        }

        if ($candidateType === 'element_clause_mapping') {
            return '要素 ' . (string)($payload['element_code'] ?? '-') . ' ← '
                . (string)($payload['source_code'] ?? '-') . ' '
                . (string)($payload['clause_number'] ?? '-');
        }

        if ($candidateType === 'position') {
            return '岗位 ' . (string)($payload['name'] ?? '-');
        }

        if ($candidateType === 'responsibility') {
            return (string)($payload['clause_number'] ?? '-') . ' ' . (string)($payload['position_name'] ?? '-') . '：' . $this->relationLabel((string)($payload['responsibility_type'] ?? ''));
        }

        if ($candidateType === 'document_section') {
            return '手册章节 ' . (string)($payload['manual_section'] ?? '-') . ' ' . (string)($payload['element_name'] ?? '');
        }

        return (string)($payload['title'] ?? $payload['name'] ?? $candidateType);
    }

    private function endpointLabel(string $type, string $key): string
    {
        $typeLabel = [
            'clause' => '条款',
            'requirement_element' => '体系要素',
            'document_section' => '手册章节',
            'document' => '程序/文件',
            'record_form_template' => '记录表格',
            'business_module' => '运行模块',
            'quality_objective' => '质量目标',
            'position' => '岗位',
        ][$type] ?? ($type !== '' ? $type : '对象');

        return $typeLabel . '：' . ($key !== '' ? $key : '-');
    }

    private function relationLabel(string $relation): string
    {
        return [
            'implemented_by' => '由其落实',
            'controlled_by' => '由其控制',
            'evidenced_by' => '形成证据',
            'operated_by' => '由模块运行',
            'maps_to' => '对应',
            'equivalent' => '等同对应',
            'partial' => '部分对应',
            'supplement' => '补充参考',
            'reference' => '参考来源',
            'decision_owner' => '决策负责',
            'organizer' => '组织落实',
            'participant' => '参与',
        ][$relation] ?? ($relation !== '' ? $relation : '-');
    }

    private function publishPayload(string $candidateType, array $payload): string
    {
        return match ($candidateType) {
            'clause' => $this->publishClause($payload),
            'requirement_element' => $this->publishRequirementElement($payload),
            'element_clause_mapping' => $this->publishElementClauseMapping($payload),
            'position' => $this->publishPosition($payload),
            'responsibility' => $this->publishResponsibility($payload),
            'document_section' => $this->publishDocumentSection($payload),
            'trace_link' => $this->publishTraceLink($payload),
            default => throw new HttpException(400, '暂不支持发布的候选类型：' . $candidateType),
        };
    }

    private function publishCandidatePayload(QmsImportCandidate $candidate, array $payload): string
    {
        $publishedId = $this->publishPayload((string)$candidate->candidate_type, $payload);
        $candidate->save([
            'status' => 'published',
            'published_record_id' => $publishedId,
            'published_at' => date('Y-m-d H:i:s'),
        ]);

        return $publishedId;
    }

    private function publishClause(array $payload): string
    {
        $source = QmsSource::where('source_code', (string)($payload['source_code'] ?? ''))->where('soft_delete', 0)->find();
        if (!$source) {
            throw new HttpException(400, '请先登记正式外部依据：' . (string)($payload['source_code'] ?? ''));
        }
        if ((string)$source->status !== 'published') {
            throw new HttpException(400, '请先发布外部依据后再发布其条款：' . (string)$source->source_code);
        }

        $clause = QmsClause::where('source_id', $source->id)
            ->where('clause_number', (string)$payload['clause_number'])
            ->where('soft_delete', 0)
            ->find();
        if (!$clause) {
            $clause = new QmsClause();
            $clause->id = qms_uuid();
        }
        $clause->save([
            'source_id' => $source->id,
            'clause_number' => (string)$payload['clause_number'],
            'title' => (string)($payload['title'] ?? ''),
            'level' => (int)($payload['level'] ?? 1),
            'locator' => (string)($payload['locator'] ?? ''),
            'review_status' => 'published',
        ]);

        $originalText = trim((string)($payload['original_text'] ?? ''));
        if ($originalText !== '') {
            $text = QmsClauseText::where('clause_id', (string)$clause->id)
                ->where('soft_delete', 0)
                ->find();
            if (!$text) {
                $text = new QmsClauseText();
                $text->id = qms_uuid();
            }
            $text->save([
                'clause_id' => (string)$clause->id,
                'source_id' => (string)$source->id,
                'clause_number' => (string)$payload['clause_number'],
                'original_text' => $originalText,
                'locator' => (string)($payload['locator'] ?? ''),
                'text_hash' => hash('sha256', $originalText),
                'extraction_method' => (string)($payload['extraction_method'] ?? 'manual'),
                'review_status' => 'published',
                'review_note' => trim((string)($payload['review_note'] ?? '')),
            ]);
        }

        return (string)$clause->id;
    }

    private function publishRequirementElement(array $payload): string
    {
        $element = QmsRequirementElement::where('element_code', (string)$payload['element_code'])
            ->where('soft_delete', 0)
            ->find();
        if (!$element) {
            $element = new QmsRequirementElement();
            $element->id = qms_uuid();
        }
        $element->save([
            'element_code' => (string)$payload['element_code'],
            'name' => (string)($payload['name'] ?? $payload['element_code']),
            'manual_section' => (string)($payload['manual_section'] ?? $payload['element_code']),
            'source_basis' => (string)($payload['source_basis'] ?? ''),
            'summary' => (string)($payload['summary'] ?? ''),
            'review_status' => 'published',
        ]);

        return (string)$element->id;
    }

    private function publishElementClauseMapping(array $payload): string
    {
        $elementCode = (string)($payload['element_code'] ?? '');
        $sourceCode = (string)($payload['source_code'] ?? '');
        $clauseNumber = (string)($payload['clause_number'] ?? '');
        if ($elementCode === '' || $sourceCode === '' || $clauseNumber === '') {
            throw new HttpException(400, '要素与外部条款映射缺少必要字段');
        }

        $element = QmsRequirementElement::where('element_code', $elementCode)->where('soft_delete', 0)->find();
        $source = QmsSource::where('source_code', $sourceCode)->where('soft_delete', 0)->find();
        $mapping = QmsElementClauseMapping::where('element_code', $elementCode)
            ->where('source_code', $sourceCode)
            ->where('clause_number', $clauseNumber)
            ->where('soft_delete', 0)
            ->find();
        if (!$mapping) {
            $mapping = new QmsElementClauseMapping();
            $mapping->id = qms_uuid();
        }
        $mapping->save([
            'element_id' => $element ? (string)$element->id : null,
            'element_code' => $elementCode,
            'source_id' => $source ? (string)$source->id : null,
            'source_code' => $sourceCode,
            'clause_number' => $clauseNumber,
            'clause_title' => (string)($payload['clause_title'] ?? ''),
            'mapping_basis' => (string)($payload['mapping_basis'] ?? 'equivalent'),
            'mapping_source' => (string)($payload['mapping_source'] ?? ''),
            'review_status' => 'published',
        ]);

        return (string)$mapping->id;
    }

    private function publishPosition(array $payload): string
    {
        $position = QmsPosition::where('code', (string)$payload['code'])->where('soft_delete', 0)->find();
        if (!$position) {
            $position = new QmsPosition();
            $position->id = qms_uuid();
        }
        $position->save([
            'code' => (string)$payload['code'],
            'name' => (string)$payload['name'],
            'source' => (string)($payload['source'] ?? ''),
            'review_status' => 'published',
        ]);

        return (string)$position->id;
    }

    private function publishResponsibility(array $payload): string
    {
        $matrix = new QmsResponsibilityMatrix();
        $matrix->id = qms_uuid();
        $matrix->save([
            'clause_number' => (string)($payload['clause_number'] ?? ''),
            'position_name' => (string)($payload['position_name'] ?? ''),
            'responsibility_type' => (string)($payload['responsibility_type'] ?? 'participant'),
            'raw_symbol' => (string)($payload['raw_symbol'] ?? ''),
            'source_style' => (string)($payload['source_style'] ?? ''),
            'review_status' => 'published',
        ]);

        return (string)$matrix->id;
    }

    private function publishDocumentSection(array $payload): string
    {
        $section = QmsDocumentSection::where('section_number', (string)$payload['manual_section'])
            ->where('soft_delete', 0)
            ->find();
        if (!$section) {
            $section = new QmsDocumentSection();
            $section->id = qms_uuid();
        }
        $section->save([
            'section_number' => (string)$payload['manual_section'],
            'title' => (string)($payload['element_name'] ?? $payload['manual_section']),
            'level' => substr_count((string)$payload['manual_section'], '.') + 1,
            'summary' => 'CNAS/GB/T 对照：' . (string)($payload['cnas_clause'] ?? '') . '；CMA 对照：' . (string)($payload['cma_clause'] ?? ''),
            'review_status' => 'published',
        ]);

        return (string)$section->id;
    }

    private function publishTraceLink(array $payload): string
    {
        $link = QmsTraceLink::where('source_type', (string)$payload['source_type'])
            ->where('source_key', (string)($payload['source_key'] ?? ''))
            ->where('target_type', (string)$payload['target_type'])
            ->where('target_key', (string)($payload['target_key'] ?? ''))
            ->where('relation_type', (string)($payload['relation_type'] ?? 'maps_to'))
            ->where('soft_delete', 0)
            ->find();
        if (!$link) {
            $link = new QmsTraceLink();
            $link->id = qms_uuid();
        }
        $link->save([
            'source_type' => (string)$payload['source_type'],
            'source_key' => (string)($payload['source_key'] ?? ''),
            'target_type' => (string)$payload['target_type'],
            'target_key' => (string)($payload['target_key'] ?? ''),
            'relation_type' => (string)($payload['relation_type'] ?? 'maps_to'),
            'evidence_note' => (string)($payload['evidence_note'] ?? $payload['target_title'] ?? ''),
            'review_status' => 'published',
        ]);

        return (string)$link->id;
    }

    private function refreshBatchCounts(string $batchId): void
    {
        $batch = QmsImportBatch::find($batchId);
        if (!$batch) {
            return;
        }

        $batch->save([
            'published_count' => QmsImportCandidate::where('batch_id', $batchId)->where('status', 'published')->count(),
            'rejected_count' => QmsImportCandidate::where('batch_id', $batchId)->where('status', 'rejected')->count(),
            'obsolete_count' => QmsImportCandidate::where('batch_id', $batchId)->where('status', 'obsolete')->count(),
        ]);
    }

    private function findCandidate(): QmsImportCandidate
    {
        $candidate = QmsImportCandidate::where('soft_delete', 0)->find($this->request->param('id'));
        if (!$candidate) {
            throw new HttpException(404, '候选项不存在');
        }

        return $candidate;
    }

    private function countTraceTargets(string $sourceType, string $sourceKey, string $targetType): int
    {
        return QmsTraceLink::where('soft_delete', 0)
            ->where('source_type', $sourceType)
            ->where('source_key', $sourceKey)
            ->where('target_type', $targetType)
            ->where('review_status', 'published')
            ->count();
    }

    private function traceabilityChainCounts(string $elementCode): array
    {
        $manualSections = $this->traceTargetKeys('requirement_element', $elementCode, 'document_section');
        $documents = $this->traceTargetKeys('requirement_element', $elementCode, 'document');
        foreach ($manualSections as $manualSection) {
            $documents = array_merge($documents, $this->traceTargetKeys('document_section', $manualSection, 'document'));
        }
        $documents = array_values(array_unique($documents));

        $recordForms = [];
        foreach ($documents as $document) {
            $recordForms = array_merge($recordForms, $this->traceTargetKeys('document', $document, 'record_form_template'));
        }
        $recordForms = array_values(array_unique($recordForms));

        $businessModules = [];
        foreach ($recordForms as $recordForm) {
            $businessModules = array_merge($businessModules, $this->traceTargetKeys('record_form_template', $recordForm, 'business_module'));
        }
        $businessModules = array_values(array_unique($businessModules));

        return [
            'manual_sections' => count($manualSections),
            'documents' => count($documents),
            'record_forms' => count($recordForms),
            'business_modules' => count($businessModules),
        ];
    }

    private function traceStatusLabel(array $chain): string
    {
        if (($chain['manual_sections'] ?? 0) > 0 && ($chain['documents'] ?? 0) > 0) {
            return '已建立链路';
        }
        if (($chain['manual_sections'] ?? 0) > 0) {
            return '缺程序文件';
        }

        return '缺手册章节';
    }

    private function sectionHierarchyLabel(string $sectionNumber, array $sectionMap): string
    {
        $parts = explode('.', $sectionNumber);
        if (count($parts) <= 1) {
            return '顶层章节';
        }

        $parents = [];
        while (count($parts) > 1) {
            array_pop($parts);
            $parent = implode('.', $parts);
            $parents[] = isset($sectionMap[$parent]) ? $parent . ' ' . $sectionMap[$parent] : $parent;
        }

        return '上级：' . implode(' / ', array_reverse($parents));
    }

    private function traceTargetKeys(string $sourceType, string $sourceKey, string $targetType): array
    {
        $keys = [];
        foreach (QmsTraceLink::where('soft_delete', 0)
            ->where('source_type', $sourceType)
            ->where('source_key', $sourceKey)
            ->where('target_type', $targetType)
            ->where('review_status', 'published')
            ->select() as $link) {
            $key = trim((string)$link->target_key);
            if ($key !== '') {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }

    private function decorateSources(iterable $sources): void
    {
        foreach ($sources as $source) {
            $source->setAttr('status_label', $this->statusLabel((string)$source->status));
            $source->setAttr('source_type_label', $this->sourceTypeLabel((string)$source->source_type));
            $noteFields = $this->sourceReviewNoteFields((string)$source->review_note);
            foreach ($noteFields as $field => $value) {
                $source->setAttr($field, $value);
            }
        }
    }

    private function sourceTypeOptions(): array
    {
        return [
            'external_standard' => '标准',
            'external_guidance' => '应用说明',
            'external_regulation' => '法规/评审准则',
            'other' => '其他正式依据',
        ];
    }

    private function sourceTypeLabel(string $sourceType): string
    {
        return $this->sourceTypeOptions()[$sourceType] ?? ($sourceType !== '' ? $sourceType : '-');
    }

    private function composeSourceReviewNote(): string
    {
        $lines = [];
        $checkDate = trim((string)$this->request->post('check_date', ''));
        $officialUrl = trim((string)$this->request->post('official_url', ''));
        $checkResult = trim((string)$this->request->post('check_result', ''));
        $reviewNote = trim((string)$this->request->post('review_note', ''));

        if ($checkDate !== '') {
            $lines[] = '查新日期：' . $checkDate;
        }
        if ($officialUrl !== '') {
            $lines[] = '官方来源链接：' . $officialUrl;
        }
        if ($checkResult !== '') {
            $lines[] = '查新结论：' . $checkResult;
        }
        if ($reviewNote !== '') {
            $lines[] = '复核备注：' . $reviewNote;
        }

        return implode("\n", $lines);
    }

    private function sourceReviewNoteFields(string $reviewNote): array
    {
        $fields = [
            'source_check_date' => '',
            'source_official_url' => '',
            'source_check_result' => '',
            'source_review_comment' => $reviewNote,
        ];
        if ($reviewNote === '') {
            return $fields;
        }

        $remaining = [];
        $hasStructuredLine = false;
        foreach (preg_split('/\R/u', $reviewNote) ?: [] as $line) {
            $line = trim((string)$line);
            if ($line === '') {
                continue;
            }
            if (str_starts_with($line, '查新日期：')) {
                $fields['source_check_date'] = trim(substr($line, strlen('查新日期：')));
                $hasStructuredLine = true;
                continue;
            }
            if (str_starts_with($line, '官方来源链接：')) {
                $fields['source_official_url'] = trim(substr($line, strlen('官方来源链接：')));
                $hasStructuredLine = true;
                continue;
            }
            if (str_starts_with($line, '查新结论：')) {
                $fields['source_check_result'] = trim(substr($line, strlen('查新结论：')));
                $hasStructuredLine = true;
                continue;
            }
            if (str_starts_with($line, '复核备注：')) {
                $remaining[] = trim(substr($line, strlen('复核备注：')));
                $hasStructuredLine = true;
                continue;
            }
            $remaining[] = $line;
        }

        if ($hasStructuredLine) {
            $fields['source_review_comment'] = implode("\n", $remaining);
        }

        return $fields;
    }

    private function findSource(): QmsSource
    {
        $id = trim((string)$this->request->param('id', ''));
        $source = $id !== '' ? QmsSource::where('soft_delete', 0)->find($id) : null;
        if (!$source) {
            throw new HttpException(404, '未找到外部依据记录');
        }

        return $source;
    }

    private function clauseTextMap(iterable $clauses): array
    {
        $ids = [];
        foreach ($clauses as $clause) {
            $ids[] = (string)$clause->id;
        }
        $ids = array_values(array_filter(array_unique($ids)));
        if (empty($ids)) {
            return [];
        }

        $map = [];
        foreach (QmsClauseText::where('soft_delete', 0)
            ->where('clause_id', 'in', $ids)
            ->select() as $text) {
            $map[(string)$text->clause_id] = $text;
        }

        return $map;
    }

    private function sourceMap(): array
    {
        $map = [];
        foreach (QmsSource::where('soft_delete', 0)->select() as $source) {
            $map[(string)$source->id] = (string)$source->source_code;
        }

        return $map;
    }

    private function documentMap(): array
    {
        $map = [];
        foreach (Document::where('soft_delete', 0)->select() as $document) {
            $map[(string)$document->id] = trim((string)$document->doc_number . ' ' . (string)$document->title);
        }

        return $map;
    }
}
