<?php
declare(strict_types=1);

$root = dirname(__DIR__);

function assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

function assert_not_contains(string $needle, string $haystack, string $message): void
{
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Unexpected: ' . $needle . PHP_EOL);
        exit(1);
    }
}

$route = (string)file_get_contents($root . '/route/app.php');
$config = (string)file_get_contents($root . '/config/qms.php');
$layout = (string)file_get_contents($root . '/app/view/layout/main.html');
$controller = (string)file_get_contents($root . '/app/controller/Planning.php');
$sourcesView = (string)file_get_contents($root . '/app/view/planning/sources.html');
$clausesView = (string)file_get_contents($root . '/app/view/planning/clauses.html');
$importBatchView = (string)file_get_contents($root . '/app/view/planning/import_batches.html');
$elementsView = (string)file_get_contents($root . '/app/view/planning/elements.html');
$traceabilityView = (string)file_get_contents($root . '/app/view/planning/traceability.html');
$documentSectionsView = (string)file_get_contents($root . '/app/view/planning/document_sections.html');

foreach ([
    'planning/sources',
    'planning/clauses',
    'planning/elements',
    'planning/positions',
    'planning/responsibility-matrix',
    'planning/objectives',
    'planning/document-sections',
    'planning/traceability',
    'planning/import-batches',
] as $path) {
    assert_contains($path, $route, 'Route exposes ' . $path);
    assert_contains('/' . $path, $layout, 'Navigation exposes ' . $path);
}

assert_contains("'planning'", $config, 'Quality manager permissions include planning');
assert_contains("{:session('success')}", $layout, 'Layout renders success flash message content');
assert_contains("{:session('warning')}", $layout, 'Layout renders warning flash message content');
assert_contains("{:session('error')}", $layout, 'Layout renders error flash message content');
assert_not_contains('$Think.session.success', $layout, 'Layout should not render empty flash message placeholders');
assert_contains('seedSources', $controller, 'Planning controller can register official sources');
assert_contains('createSourceCandidate', $controller, 'Planning controller can create source candidates');
assert_contains('uploadSourceCandidate', $controller, 'Planning controller can upload source files as candidates');
assert_contains('checkSourceCandidate', $controller, 'Planning controller can record source freshness checks before publishing');
assert_contains('createSourceClauseCandidates', $controller, 'Planning controller can create clause candidates from a registered source file');
assert_contains('model_summarized', $controller . $sourcesView . $importBatchView, 'Planning UI explains first-batch model-summarized clause titles');
assert_contains('模型标题/结构化条款', $sourcesView, 'External source page labels first-batch model title generation');
assert_contains('publishSourceCandidate', $controller, 'Planning controller can publish reviewed source candidates');
assert_contains('obsoleteSource', $controller, 'Planning controller can obsolete source records');
assert_contains('createManualCandidates', $controller, 'Planning controller can create manual-derived candidates');
assert_contains('elements', $controller, 'Planning controller exposes requirement element center');
assert_contains('createTraceabilitySample', $controller, 'Planning controller can create 6.2 traceability candidates');
assert_contains('syncInternalDocuments', $controller, 'Planning controller can directly register the current manual and matched procedure files');
assert_contains('buildInternalDocumentBaselines', $controller, 'Planning controller uses the internal document baseline service');
assert_contains('publishCandidate', $controller, 'Planning controller supports review publishing');
assert_contains('publishCandidateBatch', $controller, 'Planning controller supports batch publishing reviewed candidates');
assert_contains('publishCandidatePayload', $controller, 'Planning controller reuses the same publish path for single and batch publishing');
assert_contains('updateClauseCandidate', $controller, 'Planning controller supports manual correction before publishing clause candidates');
assert_contains('createPolicy', $controller, 'Planning controller can create quality policies');
assert_contains('createObjective', $controller, 'Planning controller can create quality objectives');
assert_contains('decorateCandidates', $controller, 'Planning controller decorates raw import payloads for human review');
assert_contains('selectedBatch', $controller, 'Planning controller exposes selected batch context for review');
assert_contains('candidatePages', $controller, 'Planning controller renders candidate pagination');
assert_contains('replaceCandidateBatch', $controller, 'Planning controller replaces old source-clause candidate batches for the same source');
assert_contains('replaceSourceClauseBatches', $controller, 'Planning controller clears previous source-clause candidates before regenerating a source');
assert_contains('statusLabel', $controller, 'Planning controller translates review statuses for human review');
assert_contains('batchTypeLabel', $controller, 'Planning controller translates batch types for human review');
assert_contains('clauseTextMap', $controller, 'Planning controller loads clause original text separately');
assert_contains('clause_review_note', $controller, 'Planning controller exposes clause interpretation notes separately');
assert_contains('clauseDisplaySortKey', $controller, 'Planning controller sorts clauses by hierarchy path rather than raw strings');
assert_contains('Paginator::make', $controller, 'Planning controller paginates after hierarchy-aware clause sorting');
assert_contains('clauseStatusLabel', $controller, 'Planning controller translates clause statuses');
assert_contains('applicabilityLabel', $controller, 'Planning controller translates clause applicability');
assert_contains('payload_summary', $importBatchView, 'Import review view renders a human-readable payload summary');
assert_contains('candidate_type_label', $importBatchView, 'Import review view renders human-readable candidate type labels');
assert_contains('status_label', $importBatchView, 'Import review view renders translated statuses');
assert_contains('batch_type_label', $importBatchView, 'Import review view renders translated batch types');
assert_contains('candidatePages', $importBatchView, 'Import review view renders pagination for large candidate batches');
assert_contains('<details', $importBatchView, 'Import review view hides raw JSON in an expandable detail');
assert_contains('查看逐字原文', $importBatchView, 'Import review view shows clause original text separately from raw JSON');
assert_contains('payload_original_text', $controller, 'Planning controller exposes clause original text for import review');
assert_contains('clauseCandidateNote', $controller, 'Planning controller marks clause title source and locator for reviewers');
assert_contains('JSON_INVALID_UTF8_SUBSTITUTE', $controller, 'Import candidate payloads tolerate imperfect PDF text encoding');
assert_contains('/planning/updateClauseCandidate', $importBatchView, 'Import review view exposes clause candidate correction form');
assert_contains('/planning/publishCandidateBatch', $importBatchView, 'Import review view exposes batch publishing for reviewed candidates');
assert_contains('批量发布待复核候选', $importBatchView, 'Import review view labels batch publishing clearly');
assert_contains('仅发布仍为待复核的候选', $importBatchView, 'Import review view explains batch publishing boundary');
assert_contains('修正候选', $importBatchView, 'Import review view labels manual correction clearly');
assert_contains('original_text', $importBatchView, 'Import review view lets reviewers correct clause original text');
assert_contains('条文释义 / 审核提示', $clausesView, 'Clause library view renders interpretation notes when available');
assert_contains('qms-clause-tree-cell', $clausesView, 'Clause library view uses tree cells to make published clause structure visible');
assert_contains('clause_hierarchy_label', $clausesView, 'Clause library view shows the parent hierarchy for easier review');

foreach ([
    'planning/createSourceCandidate',
    'planning/uploadSourceCandidate',
    'planning/checkSourceCandidate',
    'planning/createSourceClauseCandidates',
    'planning/updateClauseCandidate',
    'planning/publishCandidateBatch',
    'planning/publishSourceCandidate',
    'planning/obsoleteSource',
] as $path) {
    assert_contains($path, $route, 'Route exposes ' . $path);
}

foreach ([
    '登记依据候选',
    '优先上传正式文件自动预填',
    '手工补录依据候选',
    '同一待复核列表',
    '/planning/uploadSourceCandidate',
    'enctype="multipart/form-data"',
    'source_file',
    '自动预填编号、名称和版本',
    'A\\d{3}',
    '珠宝玉石',
    '/planning/createSourceCandidate',
    '登记手工候选',
    '待复核依据候选',
    '查新/补齐',
    '结构化条款',
    '查看条款',
    '保存查新',
    '后续接入智能体',
    'createSourceClauseCandidates',
    'publishSourceCandidate',
    'checkSourceCandidate',
    'obsoleteSource',
    '查新结论',
    'source_code',
    'name',
    'version',
    'effective_date',
    'source_type',
    'attachment_file_path',
    'attachment_file_name',
    'official_url',
    'check_result',
    'review_note',
] as $needle) {
    assert_contains($needle, $sourcesView, 'External source page supports reviewed local source registration: ' . $needle);
}

assert_not_contains('新增依据候选', $sourcesView, 'External source page should present manual entry as a fallback, not a duplicate main flow');

foreach ([
    '条款候选请从具体依据文件发起',
    '本页仅复核',
    '同一依据重新生成会覆盖旧候选',
    'source_clauses',
    'current_manual',
    'sample_6_2',
    '批次类型',
    '暂无结构化复核批次',
] as $needle) {
    assert_contains($needle, $importBatchView . $controller, 'Import review page supports second-phase review center: ' . $needle);
}
assert_not_contains("where('batch_type', 'source_clauses')", $controller, 'Import review page should no longer hide manual baseline and traceability batches');

foreach ([
    '生成依据条款候选',
] as $needle) {
    assert_not_contains($needle, $importBatchView, 'Import review page should not expose broad generation buttons: ' . $needle);
}

foreach ([
    '/planning/createManualCandidates',
    '/planning/syncInternalDocuments',
    '生成现用手册基线候选',
    '登记现用手册与匹配程序文件',
    '候选批次复核',
] as $needle) {
    assert_contains($needle, $elementsView . $controller . $route, 'Requirement element center exposes the next baseline workflow: ' . $needle);
}

foreach ([
    '补充依据映射',
    '责任岗位',
    '链路状态',
    'source_supplement_count',
    'trace_status_label',
] as $needle) {
    assert_contains($needle, $elementsView . $controller, 'Requirement element center shows reviewable baseline coverage: ' . $needle);
}

foreach ([
    'qms-clause-tree-cell',
    'section_hierarchy_label',
    'document_title',
    '质量手册章节',
] as $needle) {
    assert_contains($needle, $documentSectionsView . $controller, 'Document section page makes manual chapter structure readable: ' . $needle);
}

foreach ([
    '外部条款',
    '体系要素',
    '质量手册章节',
    '程序文件',
    '记录表格',
    '运行模块',
    '6.2 人员链路',
    'traceability_chain',
] as $needle) {
    assert_contains($needle, $traceabilityView . $controller, 'Traceability matrix presents the planned chain: ' . $needle);
}

foreach ([
    '当前正式依据',
    '内置正式依据清单',
    '维护工具',
    '补齐内置正式依据',
    '仅用于初始化或修复缺失依据',
] as $needle) {
    assert_contains($needle, $sourcesView, 'External source page uses production wording for built-in sources: ' . $needle);
}

foreach ([
    '第一批基线',
    '同步第一批',
    '第一批依据清单',
] as $needle) {
    assert_not_contains($needle, $sourcesView, 'External source page should not expose development wording: ' . $needle);
}

foreach ([
    'qms-page-header',
    'qms-section-title',
    'qms-review-note',
    '<details',
    'btn-outline-danger',
    '暂无待复核依据候选',
] as $needle) {
    assert_contains($needle, $sourcesView, 'External source page follows UI design guidelines: ' . $needle);
}

foreach ([
    '正式依据',
    '条款号',
    '标题/原文关键词',
    '适用性',
    '查看原文',
    '暂无原文，请先从来源附件生成原文候选。',
    'clause_original_text',
    'clause_text_status_label',
    'qms-clause-original-text',
    'source_id',
    'clause_number',
    'keyword',
    'applicability',
] as $needle) {
    assert_contains($needle, $clausesView, 'Clause library supports source filtering, search, and expandable original text: ' . $needle);
}

foreach ([
    'sources',
    'clauses',
    'elements',
    'positions',
    'responsibility_matrix',
    'objectives',
    'document_sections',
    'traceability',
    'import_batches',
] as $viewDir) {
    assert_contains('planning/' . $viewDir, $controller, 'Planning controller renders ' . $viewDir . ' view');
}

echo "qms_planning_ui_smoke passed\n";
