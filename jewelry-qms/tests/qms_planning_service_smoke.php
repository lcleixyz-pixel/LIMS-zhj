<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\QmsPlanningImportService;

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$sources = QmsPlanningImportService::officialSourceManifest();
assert_same(4, count($sources), 'Official source manifest contains exactly four formal external sources');
assert_same(
    ['CNAS-CL01:2018', 'CNAS-CL01-G001:2024', 'GB/T 27025-2019', '市场监管总局公告2023年第21号'],
    array_column($sources, 'source_code'),
    'Official source manifest uses normalized official identifiers'
);
foreach ($sources as $source) {
    assert_true(is_file($source['absolute_path']), 'Official source file exists: ' . $source['source_code']);
    assert_true($source['status'] === 'published', 'Official sources are registered as published baselines');
}

assert_same('decision_owner', QmsPlanningImportService::normalizeResponsibilitySymbol('★', 'current_manual'), 'Current manual star maps to decision owner');
assert_same('organizer', QmsPlanningImportService::normalizeResponsibilitySymbol('●', 'current_manual'), 'Current manual filled dot maps to organizer');
assert_same('participant', QmsPlanningImportService::normalizeResponsibilitySymbol('○', 'current_manual'), 'Current manual hollow dot maps to participant');
assert_same('decision_owner', QmsPlanningImportService::normalizeResponsibilitySymbol('●', 'reference_manual'), 'Reference manual filled dot maps to decision owner');
assert_same('organizer', QmsPlanningImportService::normalizeResponsibilitySymbol('■', 'reference_manual'), 'Reference manual square maps to organizer');
assert_same('participant', QmsPlanningImportService::normalizeResponsibilitySymbol('▲', 'reference_manual'), 'Reference manual triangle maps to participant');

$parsedCl01 = QmsPlanningImportService::parseSourceFilename('04-CNAS-CL01 检测和校准实验室能力认可准则 2018 年 09 月 01 日实施.pdf');
assert_same('CNAS-CL01:2018', $parsedCl01['source_code'], 'Parses CNAS-CL01 official code from local file name');
assert_same('检测和校准实验室能力认可准则', $parsedCl01['name'], 'Parses CNAS-CL01 title from local file name');
assert_same('2018', $parsedCl01['version'], 'Parses CNAS-CL01 version from local file name');
assert_same('external_standard', $parsedCl01['source_type'], 'Parses CNAS-CL01 source type from local file name');

$parsedGuidance = QmsPlanningImportService::parseSourceFilename('03+-CNAS-CL01-G001：2024《检测和校准实验室能力认可准则的应用要求》.pdf');
assert_same('CNAS-CL01-G001:2024', $parsedGuidance['source_code'], 'Parses CNAS guidance official code from local file name');
assert_same('检测和校准实验室能力认可准则的应用要求', $parsedGuidance['name'], 'Parses CNAS guidance title from local file name');
assert_same('external_guidance', $parsedGuidance['source_type'], 'Parses CNAS guidance source type from local file name');

$parsedJewelryGuidance = QmsPlanningImportService::parseSourceFilename('CNAS-CL01-A015_2018《检测和校准实验室能力认可准则在珠宝玉石、贵金属检测领域的应用说明》.pdf');
assert_same('CNAS-CL01-A015:2018', $parsedJewelryGuidance['source_code'], 'Parses CNAS jewelry application guidance official code from local file name');
assert_same('检测和校准实验室能力认可准则在珠宝玉石、贵金属检测领域的应用说明', $parsedJewelryGuidance['name'], 'Parses CNAS jewelry application guidance title from local file name');
assert_same('2018', $parsedJewelryGuidance['version'], 'Parses CNAS jewelry application guidance version from local file name');
assert_same('external_guidance', $parsedJewelryGuidance['source_type'], 'Parses CNAS jewelry application guidance source type from local file name');

$parsedGb = QmsPlanningImportService::parseSourceFilename('05-GBT 27025-2019 检测和校准实验室能力的通用要求.pdf');
assert_same('GB/T 27025-2019', $parsedGb['source_code'], 'Parses GB/T official code from local file name');
assert_same('检测和校准实验室能力的通用要求', $parsedGb['name'], 'Parses GB/T title from local file name');

$gbSource = array_values(array_filter($sources, fn (array $row): bool => $row['source_code'] === 'GB/T 27025-2019'))[0];
$modelTitledGb = QmsPlanningImportService::buildRegisteredSourceClauseCandidates([
    'id' => 'source-gbt27025',
    'source_code' => 'GB/T 27025-2019',
    'status' => 'published',
    'attachment_file_path' => $gbSource['relative_path'],
    'attachment_file_name' => $gbSource['file_name'],
]);
assert_true(count($modelTitledGb) > 80, 'First-batch GB/T 27025 extracts original text from the registered source file');
$modelTitledMethods = array_values(array_unique(array_map(
    fn (array $row): string => (string)($row['payload']['extraction_method'] ?? ''),
    $modelTitledGb
)));
assert_same(['registered_source_text'], $modelTitledMethods, 'First-batch original text remains file-extracted');
$modelTitledGbByNumber = [];
foreach ($modelTitledGb as $candidate) {
    $modelTitledGbByNumber[(string)$candidate['payload']['clause_number']] = $candidate['payload'];
}
assert_same('技术记录内容', $modelTitledGbByNumber['7.5.1']['title'] ?? '', 'Model-generated title summarizes extracted GB/T 27025 clause text');
assert_same('model_summarized', $modelTitledGbByNumber['7.5.1']['title_source'] ?? '', 'First-batch titles are marked as model summaries');
assert_same('人员', $modelTitledGbByNumber['6.2']['title'] ?? '', 'Original headings are kept directly when the extracted text is already a heading');
assert_same('known_heading', $modelTitledGbByNumber['6.2']['title_source'] ?? '', 'Kept original headings are not relabeled as model summaries');
assert_true(
    !preg_match('/^\d/u', (string)($modelTitledGbByNumber['7.5.1']['title'] ?? '')),
    'Generated review titles do not include clause numbers'
);
assert_true(
    str_contains((string)($modelTitledGbByNumber['7.5.1']['original_text'] ?? ''), '实验室应确保每一项实验室活动的技术记录包含结果报告和足够的信息'),
    'Model-generated titles keep the automatically extracted original text for review'
);
assert_true(
    str_contains((string)($modelTitledGbByNumber['7.5.1']['manual_review_note'] ?? ''), '原文来自附件自动抽取'),
    'First-batch candidates carry an automatic extraction check note'
);
assert_true(
    ($modelTitledGbByNumber['7.5.1']['quality_check']['model_outline_match'] ?? false) === true,
    'First-batch candidates record model outline match checks'
);

$cmaSource = array_values(array_filter($sources, fn (array $row): bool => $row['source_code'] === '市场监管总局公告2023年第21号'))[0];
$modelTitledCma = QmsPlanningImportService::buildRegisteredSourceClauseCandidates([
    'id' => 'source-cma2023',
    'source_code' => '市场监管总局公告2023年第21号',
    'status' => 'published',
    'attachment_file_path' => $cmaSource['relative_path'],
    'attachment_file_name' => $cmaSource['file_name'],
]);
$modelTitledCmaByNumber = [];
foreach ($modelTitledCma as $candidate) {
    $modelTitledCmaByNumber[(string)$candidate['payload']['clause_number']] = $candidate['payload'];
}
$modelTitledCmaNumbers = array_map(fn (array $candidate): string => (string)$candidate['payload']['clause_number'], $modelTitledCma);
assert_true(
    array_search('2.8.1*', $modelTitledCmaNumbers, true) < array_search('2.8.2', $modelTitledCmaNumbers, true),
    'CMA key starred items sort next to their natural clause position'
);
assert_true(
    strcmp((string)($modelTitledCmaByNumber['2.8']['sort_key'] ?? ''), (string)($modelTitledCmaByNumber['2.8.1*']['sort_key'] ?? '')) < 0,
    'CMA parent clause sort key comes before starred child item in review pages'
);
assert_true(isset($modelTitledCmaByNumber['2.8.1*']), 'CMA key starred item 2.8.1* is extracted as a standalone candidate');
assert_same('法律地位和责任', $modelTitledCmaByNumber['2.8.1*']['title'] ?? '', 'CMA key starred item has its own review title');
assert_true(($modelTitledCmaByNumber['2.8.1*']['is_key_item'] ?? false) === true, 'CMA starred items are marked as key items');
assert_true(!str_contains((string)($modelTitledCmaByNumber['2.8']['original_text'] ?? ''), '2.8.1*'), 'CMA key starred item is not merged into parent 2.8');
assert_true(isset($modelTitledCmaByNumber['2.12.4*']), 'CMA key starred item 2.12.4* is extracted as a standalone candidate');
assert_same('方法验证和确认', $modelTitledCmaByNumber['2.12.4*']['title'] ?? '', 'CMA method verification key item has the correct review title');
assert_same('记录管理', $modelTitledCmaByNumber['2.12.7']['title'] ?? '', 'Model-generated titles match CMA 2023 review-rule numbering');
assert_same('特殊要求', $modelTitledCmaByNumber['2.13']['title'] ?? '', 'CMA special requirements keeps a meaningful complete title');
assert_same('model_summarized', $modelTitledCmaByNumber['2.12.7']['title_source'] ?? '', 'CMA model title summaries are applied to extracted text');

$applyModelSummariesMethod = new ReflectionMethod(QmsPlanningImportService::class, 'applyModelSummariesToExtractedRows');
$a015TitleRows = $applyModelSummariesMethod->invoke(null, [[
    'clause_number' => '7.5.1',
    'title' => '检测过程中所有观测的条件和观测结果均应予记录',
    'raw_heading' => '',
    'title_source' => 'auto_simplified',
    'locator' => 'text-line:1',
    'original_text' => '7.5.1 检测过程中所有观测的条件和观测结果均应予记录，适当时，可借助草图或示意图或电子照片来进行记录。',
]], 'CNAS-CL01-A015:2018');
assert_same('检测观测记录', $a015TitleRows[0]['title'] ?? '', 'Generated titles avoid mechanical requirement suffixes');

$a015BaselineCandidates = QmsPlanningImportService::applyPublishedReviewTitleBaseline([[
    'candidate_type' => 'clause',
    'payload' => [
        'source_code' => 'CNAS-CL01-A015:2018',
        'clause_number' => '6.4.5',
        'title' => '定性检测设备校准',
        'title_source' => 'model_summarized',
        'quality_check' => [
            'original_text_source' => 'registered_source_text',
            'title_source' => 'model_summarized',
            'model_outline_match' => true,
            'warnings' => [],
        ],
        'manual_review_note' => '标题由模型生成；原文来自附件自动抽取。',
    ],
]], ['6.4.5' => '检测设备校准的要求']);
assert_same('检测设备校准的要求', $a015BaselineCandidates[0]['payload']['title'] ?? '', 'Published A015 review title can be applied as same-source candidate baseline');
assert_same('published_review_baseline', $a015BaselineCandidates[0]['payload']['title_source'] ?? '', 'Same-source A015 baseline title is traceable');

$gbWithA015Baseline = QmsPlanningImportService::applyPublishedReviewTitleBaseline([[
    'candidate_type' => 'clause',
    'payload' => [
        'source_code' => 'GB/T 27025-2019',
        'clause_number' => '6.2.1',
        'title' => '人员公正与能力',
        'title_source' => 'model_summarized',
    ],
]], ['6.2.1' => '检测人员固定性']);
assert_same('人员公正与能力', $gbWithA015Baseline[0]['payload']['title'] ?? '', 'A015 jewelry-specific titles are not copied to other formal sources by clause number alone');

$g001TitleRows = $applyModelSummariesMethod->invoke(null, [[
    'clause_number' => '7.4.1',
    'title' => '条款内容',
    'raw_heading' => '',
    'title_source' => 'auto_simplified',
    'locator' => 'text-line:1',
    'original_text' => '7.4.1 实验室应确保样品处置和客户信息安全，并保留相关记录。',
]], 'CNAS-CL01-G001:2024');
assert_same('样品处理和客户信息安全', $g001TitleRows[0]['title'] ?? '', 'G001 detailed clause titles replace generic placeholder summaries');

$g001ImpartialityRows = $applyModelSummariesMethod->invoke(null, [[
    'clause_number' => '4.1.4',
    'title' => '条款内容',
    'raw_heading' => '',
    'title_source' => 'auto_simplified',
    'locator' => 'text-line:1',
    'original_text' => '4.1.4 实验室应在任何可能发生影响公正性的事件时持续不断的识别风险。',
]], 'CNAS-CL01-G001:2024');
assert_same('公正性风险持续识别', $g001ImpartialityRows[0]['title'] ?? '', 'G001 impartiality risk clause has a reviewable title');

$cmaMarkdownRows = QmsPlanningImportService::parseCma2023MarkdownClauses(
    file_get_contents('/Users/lc.leixyz/Downloads/检验检测机构资质认定评审准则2023.md') ?: '',
    file_get_contents('/Users/lc.leixyz/Downloads/《检验检测机构资质认定评审准则》条文释义.txt') ?: ''
);
$cmaMarkdownByNumber = [];
foreach ($cmaMarkdownRows as $row) {
    $cmaMarkdownByNumber[(string)$row['clause_number']] = $row;
}
assert_true(count($cmaMarkdownRows) > 100, 'CMA 2023 markdown parser extracts the main rule and attachment structure');
assert_same('法律地位和责任', $cmaMarkdownByNumber['第八条（一）']['title'] ?? '', 'CMA article subitem keeps reviewable official-article numbering');
assert_true(
    str_contains((string)($cmaMarkdownByNumber['第八条（一）']['original_text'] ?? ''), '不具备独立法人资格的检验检测机构应当经所在法人单位授权'),
    'CMA article subitem keeps complete original text from markdown'
);
assert_true(
    str_contains((string)($cmaMarkdownByNumber['第八条（一）']['review_note'] ?? ''), '依法设立的法人'),
    'CMA interpretation text is attached as review note rather than mixed into official text'
);
assert_same('记录管理', $cmaMarkdownByNumber['第十二条（七）']['title'] ?? '', 'CMA management-system subitems get concise titles');
assert_true(isset($cmaMarkdownByNumber['附件1.4.4.7']), 'CMA markdown parser extracts attachment work-procedure clauses');
assert_true(
    QmsPlanningImportService::clauseDisplaySortToken('第九条') < QmsPlanningImportService::clauseDisplaySortToken('第十条'),
    'Clause display sort handles Chinese article numbers naturally'
);
assert_true(
    QmsPlanningImportService::clauseDisplaySortToken('附件1.4.4.9') < QmsPlanningImportService::clauseDisplaySortToken('附件1.4.4.11'),
    'Clause display sort handles dotted attachment numbers naturally'
);

$extractPdfTextMethod = new ReflectionMethod(QmsPlanningImportService::class, 'extractPdfText');
$temporaryPdfPath = tempnam(sys_get_temp_dir(), 'qms-pdf-sidecar-');
assert_true($temporaryPdfPath !== false, 'Can create temporary PDF placeholder');
$temporaryPdfPath .= '.pdf';
file_put_contents($temporaryPdfPath, "%PDF-1.4\n% scanned placeholder\n");
file_put_contents(substr($temporaryPdfPath, 0, -4) . '.txt', "6.2 人员\n人员应具备能力。\n");
$sidecarText = $extractPdfTextMethod->invoke(null, $temporaryPdfPath);
assert_true(str_contains($sidecarText, '人员应具备能力'), 'Scanned PDFs can use same-name OCR sidecar text');
@unlink($temporaryPdfPath);
@unlink(substr($temporaryPdfPath, 0, -4) . '.txt');

$parsedCma = QmsPlanningImportService::parseSourceFilename('06-新检验检测机构资质认定评审准则2023带附件.docx');
assert_same('市场监管总局公告2023年第21号', $parsedCma['source_code'], 'Parses CMA 2023 review rule official announcement code from local file name');
assert_same('检验检测机构资质认定评审准则', $parsedCma['name'], 'Parses CMA 2023 review rule title from local file name');
assert_same('external_regulation', $parsedCma['source_type'], 'Parses CMA 2023 review rule source type from local file name');

$manual = QmsPlanningImportService::extractCurrentManualBaseline();
$positionNames = array_column($manual['positions'], 'name');
assert_true(in_array('实验室主任', $positionNames, true), 'Extracts current manual position: lab director');
assert_true(in_array('质量负责人', $positionNames, true), 'Extracts current manual position: quality manager');

$matrixClauseNumbers = array_column($manual['responsibility_matrix'], 'clause_number');
assert_true(in_array('6.2', $matrixClauseNumbers, true), 'Extracts responsibility row for personnel clause 6.2');
assert_true(
    count(array_filter(
        $manual['responsibility_matrix'],
        fn (array $row): bool => $row['clause_number'] === '6.2' && $row['responsibility_type'] === 'decision_owner'
    )) >= 1,
    'Personnel clause 6.2 has at least one decision owner in the current manual matrix'
);

$mappingManualSections = array_column($manual['manual_clause_mappings'], 'manual_section');
assert_true(in_array('4.1', $mappingManualSections, true), 'Extracts quality manual clause correspondence table');

$elements = $manual['requirement_elements'];
$elementCodes = array_column($elements, 'element_code');
assert_true(in_array('6.2', $elementCodes, true), 'Builds requirement element 6.2 from appendix 16');
$personnelElements = array_values(array_filter($elements, fn (array $row): bool => $row['element_code'] === '6.2'));
assert_same('人员', $personnelElements[0]['name'], 'Requirement element 6.2 keeps the manual element name');

$elementClauseMappings = $manual['element_clause_mappings'];
$personnelClauseSources = array_values(array_unique(array_column(array_filter(
    $elementClauseMappings,
    fn (array $row): bool => $row['element_code'] === '6.2'
), 'source_code')));
sort($personnelClauseSources);
assert_same(['CNAS-CL01-A015:2018', 'CNAS-CL01:2018', 'GB/T 27025-2019', '市场监管总局公告2023年第21号'], $personnelClauseSources, 'Element 6.2 maps to equivalent formal clauses and jewelry-field supplemental guidance using official identifiers');
assert_true(
    count(array_filter(
        $elementClauseMappings,
        fn (array $row): bool => $row['element_code'] === '6.2'
            && $row['source_code'] === 'CNAS-CL01-A015:2018'
            && ($row['mapping_basis'] ?? '') === 'supplement'
    )) >= 1,
    'Jewelry-field application guidance is kept as supplemental mapping rather than equivalent mapping'
);

$referenceSupplements = QmsPlanningImportService::extractReferenceManualSupplements();
assert_true(
    count(array_filter($referenceSupplements['procedure_mappings'], fn (array $row): bool => $row['element_code'] === '6.2' && str_contains($row['document_title'], '人员'))) >= 1,
    'Reference manual supplies procedure-to-element supplement for personnel'
);

$trainingSample = QmsPlanningImportService::trainingTraceabilitySample();
assert_same('6.2', $trainingSample['clause_number'], 'Training sample anchors on 6.2');
assert_same(
    ['XZTC/BG-01-01', 'XZTC/BG-01-02', 'XZTC/BG-01-08', 'XZTC/BG-01-09'],
    array_column($trainingSample['record_forms'], 'doc_number'),
    'Training sample connects the four agreed personnel record forms'
);
assert_same(
    ['training_plans', 'trainings', 'training_records', 'competency_records'],
    array_column($trainingSample['business_modules'], 'module_key'),
    'Training sample connects existing training and competency modules'
);

$traceCandidates = QmsPlanningImportService::buildTrainingTraceCandidates();
assert_same('requirement_element', $traceCandidates[0]['payload']['source_type'], 'Training sample starts from requirement element instead of raw external clause');
assert_true(
    count(array_filter($traceCandidates, fn (array $row): bool => ($row['payload']['source_type'] ?? '') === 'record_form_template' && ($row['payload']['target_type'] ?? '') === 'business_module')) >= 1,
    'Training sample connects business modules after record form templates in the internal chain'
);

$clauseRowsMethod = new ReflectionMethod(QmsPlanningImportService::class, 'clauseRowsFromText');
$sampleClauseRows = $clauseRowsMethod->invoke(null, implode(PHP_EOL, [
    '6.3 设施和环境条件',
    '6.3.1 实验室环境应满足相应的检测要求。如：珠宝检测实验室的环境应为中性或灰',
    '色调，照明应满足检测工作需要。',
    '6.3.2 影响检测结果的区域应有效隔离。',
]));
$sampleClause631 = array_values(array_filter($sampleClauseRows, fn (array $row): bool => $row['clause_number'] === '6.3.1'))[0] ?? null;
assert_true($sampleClause631 !== null, 'Extracts sample clause 6.3.1 from wrapped PDF text');
assert_same('珠宝检测实验室环境条件', $sampleClause631['title'], 'Simplifies body-like clause text into a short review title');
assert_true(
    str_contains($sampleClause631['original_text'], '色调，照明应满足检测工作需要。'),
    'Keeps wrapped continuation lines in the original clause text'
);
assert_same('auto_simplified', $sampleClause631['title_source'], 'Marks simplified clause titles for reviewer awareness');

$sampleReferenceRows = $clauseRowsMethod->invoke(null, implode(PHP_EOL, [
    '2 规范性引用文件',
    '2.1 CNAS-CL01-G001《CNAS-CL01<检测和校准实验室能力认可准则>应用要求》',
    '2.2 GB/T 27025 检测和校准实验室能力的通用要求',
    '3 术语和定义',
]));
$sampleReferenceNumbers = array_column($sampleReferenceRows, 'clause_number');
assert_true(in_array('2', $sampleReferenceNumbers, true), 'Keeps reference-file section as one clause');
assert_true(!in_array('2.1', $sampleReferenceNumbers, true), 'Does not split referenced files into child clauses');
assert_true(!in_array('2.2', $sampleReferenceNumbers, true), 'Does not split referenced standards into child clauses');
$sampleReferenceClause = array_values(array_filter($sampleReferenceRows, fn (array $row): bool => $row['clause_number'] === '2'))[0] ?? null;
assert_true(
    str_contains((string)($sampleReferenceClause['original_text'] ?? ''), 'CNAS-CL01-G001'),
    'Keeps referenced-file lines in the reference section original text'
);

$sampleTocRows = $clauseRowsMethod->invoke(null, implode(PHP_EOL, [
    '目次',
    '8.9 管理评审(方式 A) ee 15',
    '附录 B (资料性附录) 管理体系方式 和18',
    '前 言',
    '1 范围',
    '本标准规定了实验室能力、公正性以及一致运作的通用要求。',
    '8.9 管理评审',
    '8.9.1 实验室管理层应按照策划的时间间隔对管理体系进行评审。',
]));
$sampleTocClause89 = array_values(array_filter($sampleTocRows, fn (array $row): bool => $row['clause_number'] === '8.9'))[0] ?? null;
$sampleTocClause891 = array_values(array_filter($sampleTocRows, fn (array $row): bool => $row['clause_number'] === '8.9.1'))[0] ?? null;
assert_true($sampleTocClause89 !== null, 'Extracts real clause after front matter and table of contents');
assert_same('管理评审', $sampleTocClause89['title'], 'Ignores table-of-contents clause-like entries before the real first clause');
assert_true($sampleTocClause891 !== null, 'Keeps detailed clauses after removing table-of-contents text');
assert_true(str_contains($sampleTocClause891['original_text'], '管理体系进行评审'), 'Keeps the real clause body instead of table-of-contents text');

$sampleOcrHeadingRows = $clauseRowsMethod->invoke(null, implode(PHP_EOL, [
    '1 范围',
    '6.2 AR',
    '6.2.1 所有可能影响实验室活动的人员应行为公正、有能力。',
    '8.9 管理评审(方式 A)',
]));
$sampleOcrClause62 = array_values(array_filter($sampleOcrHeadingRows, fn (array $row): bool => $row['clause_number'] === '6.2'))[0] ?? null;
$sampleOcrClause89 = array_values(array_filter($sampleOcrHeadingRows, fn (array $row): bool => $row['clause_number'] === '8.9'))[0] ?? null;
assert_true($sampleOcrClause62 !== null, 'Extracts OCR-noisy GB/T 27025 parent clause heading');
assert_same('人员', $sampleOcrClause62['title'], 'Uses known GB/T 27025 heading when OCR parent heading is noise');
assert_same('管理评审', $sampleOcrClause89['title'] ?? '', 'Cleans OCR-decorated GB/T 27025 known heading');

$sampleGbtTitleRows = $clauseRowsMethod->invoke(null, implode(PHP_EOL, [
    '7.5.1 实验室应确保每一项实验室活动的技术记录包含结果报告和足够的信息。',
    '8.9.1 实验室管理层应按照策划的时间间隔对实验室的管理体系进行评审。',
]));
$sampleGbtTitle751 = array_values(array_filter($sampleGbtTitleRows, fn (array $row): bool => $row['clause_number'] === '7.5.1'))[0] ?? null;
$sampleGbtTitle891 = array_values(array_filter($sampleGbtTitleRows, fn (array $row): bool => $row['clause_number'] === '8.9.1'))[0] ?? null;
assert_same('技术记录内容', $sampleGbtTitle751['title'] ?? '', 'Simplifies GB/T 27025 technical record content title clearly');
assert_same('管理评审实施', $sampleGbtTitle891['title'] ?? '', 'Simplifies GB/T 27025 management review title clearly');

$sampleControlRows = $clauseRowsMethod->invoke(null, implode(PHP_EOL, [
    '5.4 对于在固定场所外实施检测活动，实验室应制定非固定场所检测活动的程序，对人员、设备、环境条件进行控制。',
    '6.4.5 对用于珠宝玉石定性检测或准确度要求不高的仪器设备，一般不需要进行校准。',
    '7.2.1.3 实验室应根据需要制定检测工作指导书。贵金属无损检测工作指导书至少包括操作要求。',
]));
$sampleTitlesByNumber = [];
foreach ($sampleControlRows as $row) {
    $sampleTitlesByNumber[$row['clause_number']] = $row['title'];
}
assert_same('非固定场所检测控制', $sampleTitlesByNumber['5.4'] ?? '', 'Prioritizes non-fixed-site control over incidental personnel wording');
assert_same('定性检测设备校准', $sampleTitlesByNumber['6.4.5'] ?? '', 'Prioritizes calibration wording over generic equipment wording');
assert_same('检测工作指导书', $sampleTitlesByNumber['7.2.1.3'] ?? '', 'Simplifies work-instruction requirements distinctly');

$sampleLetteredRows = $clauseRowsMethod->invoke(null, implode(PHP_EOL, [
    '5.2 实验室的管理层中对实验室活动全面负责的人员可以是一个人，也可以是由负责不同技术领域的多名技术人员组成的团队。',
    '5.5a) 当实验室所在的母体机构还从事实验室活动以外的活动时，实验室应说明母体机构所从事的其他活动与实验室活动之间的关系。',
    '实验室管理体系文件中不仅应明确实验室自身的组织结构，还应明确母体机构的组织结构。',
    '5.5c) 实验室所设定的文件层级、类型、数量及详略程度应确保实验室活动实施的一致性和结果的有效性。',
]));
$sampleClause52 = array_values(array_filter($sampleLetteredRows, fn (array $row): bool => $row['clause_number'] === '5.2'))[0] ?? null;
$sampleClause55a = array_values(array_filter($sampleLetteredRows, fn (array $row): bool => $row['clause_number'] === '5.5a)'))[0] ?? null;
$sampleClause55c = array_values(array_filter($sampleLetteredRows, fn (array $row): bool => $row['clause_number'] === '5.5c)'))[0] ?? null;
assert_true($sampleClause52 !== null, 'Keeps numeric clause before lettered requirement items');
assert_same('实验室活动负责人员', $sampleClause52['title'], 'Simplifies G001 management responsibility clause title clearly');
assert_true(!str_contains($sampleClause52['original_text'], '5.5a)'), 'Does not merge lettered requirement item into the previous numeric clause');
assert_true($sampleClause55a !== null, 'Extracts lettered requirement item 5.5a) as a standalone clause candidate');
assert_same('母体机构组织关系说明', $sampleClause55a['title'], 'Simplifies G001 parent-organization relationship item title clearly');
assert_true(str_contains($sampleClause55a['original_text'], '实验室自身的组织结构'), 'Keeps wrapped continuation lines for lettered requirement items');
assert_true($sampleClause55c !== null, 'Extracts lettered requirement item 5.5c) as a standalone clause candidate');
assert_same('文件层级与详略程度', $sampleClause55c['title'], 'Simplifies G001 document hierarchy item title clearly');

$sampleFooterRows = $clauseRowsMethod->invoke(null, implode(PHP_EOL, [
    '4 通用要求',
    '2018 年 03 月 01 日发布 2018 年 09 月 01 日实施',
    'CNAS-CL01-A015:2018 第 3 页共 7 页',
    '4.1 公正性',
]));
$sampleClause4 = array_values(array_filter($sampleFooterRows, fn (array $row): bool => $row['clause_number'] === '4'))[0] ?? null;
assert_true($sampleClause4 !== null, 'Extracts parent clause 4 from PDF text with footer noise');
assert_same('4 通用要求', $sampleClause4['original_text'], 'Removes publish/effective-date footers from parent clause original text');

$sampleAppendixRows = $clauseRowsMethod->invoke(null, implode(PHP_EOL, [
    '8 管理体系要求',
    '附录 A',
    '（资料性）',
    '珠宝玉石检测能力验证领域和频次',
    '能力验证活动按认可机构要求实施。',
    '附录 B 参考文件',
]));
$sampleClause8 = array_values(array_filter($sampleAppendixRows, fn (array $row): bool => $row['clause_number'] === '8'))[0] ?? null;
$sampleAppendixA = array_values(array_filter($sampleAppendixRows, fn (array $row): bool => $row['clause_number'] === '附录A'))[0] ?? null;
$sampleAppendixB = array_values(array_filter($sampleAppendixRows, fn (array $row): bool => $row['clause_number'] === '附录B'))[0] ?? null;
assert_true($sampleClause8 !== null, 'Keeps main clause before appendices');
assert_true(!str_contains($sampleClause8['original_text'], '附录 A'), 'Does not merge appendix heading into the previous clause');
assert_true($sampleAppendixA !== null, 'Extracts appendix A as a standalone clause candidate');
assert_same('珠宝玉石检测能力验证领域和频次', $sampleAppendixA['title'], 'Uses appendix descriptive heading as the review title');
assert_same('appendix_heading', $sampleAppendixA['title_source'], 'Marks appendix titles for reviewer awareness');
assert_true(str_contains($sampleAppendixA['original_text'], '能力验证活动按认可机构要求实施。'), 'Keeps appendix body text for original-text review');
assert_true($sampleAppendixB !== null, 'Extracts appendix B when title is on the same line');
assert_same('参考文件', $sampleAppendixB['title'], 'Uses same-line appendix title when present');

$internalDocuments = QmsPlanningImportService::buildInternalDocumentBaselines();
$manualDocuments = array_values(array_filter(
    $internalDocuments,
    fn (array $row): bool => ($row['document_level'] ?? 0) === 1
));
$procedureDocuments = array_values(array_filter(
    $internalDocuments,
    fn (array $row): bool => ($row['document_level'] ?? 0) === 2
));
assert_same(1, count($manualDocuments), 'Internal document baseline contains exactly one current quality manual');
assert_same('质量手册（第四版）', $manualDocuments[0]['title'] ?? '', 'Current quality manual title is normalized for document registration');
assert_true(
    str_contains((string)($manualDocuments[0]['file_path'] ?? ''), '现用文件/质量手册（第四版）.docx'),
    'Current quality manual keeps the local controlled-file path'
);
assert_true(count($procedureDocuments) >= 20, 'Internal document baseline finds the current procedure-file set');
assert_true(
    count(array_filter(
        $procedureDocuments,
        fn (array $row): bool => ($row['title'] ?? '') === '人员培训程序' && ($row['version_year'] ?? '') === '2022'
    )) === 1,
    'Procedure baseline prefers the 2022 personnel training procedure'
);
assert_true(
    count(array_filter(
        $procedureDocuments,
        fn (array $row): bool => ($row['title'] ?? '') === '人员培训程序' && ($row['version_year'] ?? '') === '2018'
    )) === 0,
    'Procedure baseline does not include older 2018 duplicate when 2022 exists'
);
assert_true(
    count(array_filter(
        $procedureDocuments,
        fn (array $row): bool => ($row['match_confidence'] ?? '') === 'high'
    )) >= 20,
    'Matched procedure files carry high-confidence registration hints'
);

echo "qms_planning_service_smoke passed\n";
