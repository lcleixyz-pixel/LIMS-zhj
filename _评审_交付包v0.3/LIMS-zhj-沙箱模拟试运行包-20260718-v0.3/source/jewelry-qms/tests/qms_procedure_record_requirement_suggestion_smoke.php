<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\QmsDocumentStructureService;
use app\service\QmsElementService;
use think\facade\Config;
use think\facade\Db;

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

function cleanup_procedure_record_suggestion_smoke(string $documentId, string $docNumber, string $suggestionTitle): void
{
    $structuredIds = Db::name('qms_structured_documents')->where('document_id', $documentId)->column('id');
    if ($structuredIds !== []) {
        $blockIds = Db::name('qms_document_blocks')->whereIn('structured_document_id', $structuredIds)->column('id');
        if ($blockIds !== []) {
            Db::name('qms_document_block_links')->whereIn('block_id', $blockIds)->delete();
            Db::name('qms_document_blocks')->whereIn('id', $blockIds)->delete();
        }
        Db::name('qms_structured_documents')->whereIn('id', $structuredIds)->delete();
    }
    Db::name('qms_document_assets')->where('document_id', $documentId)->delete();
    Db::name('qms_element_documents')->where('document_id', $documentId)->delete();
    Db::name('qms_document_change_logs')->where('document_id', $documentId)->delete();
    Db::name('documents')->where('id', $documentId)->delete();
    Db::name('qms_structured_documents')->where('doc_number', $docNumber)->delete();
    Db::name('qms_agent_suggestions')->where('title', $suggestionTitle)->delete();
}

QmsDocumentStructureService::seedAll();

$documentId = 'smoke-prc-record-suggest-doc';
$structuredId = 'smoke-prc-record-suggest-struct';
$blockId = 'smoke-prc-record-suggest-block';
$docNumber = 'QP-COVERAGE-SUGGEST';
$docTitle = '记录要求建议缺口程序';
$suggestionTitle = '复核程序记录要求：' . $docNumber;
$sourcePath = '现用文件/程序文件/程序文件2022/13-2022内部沟通程序.docx';
$now = date('Y-m-d H:i:s');

try {
    cleanup_procedure_record_suggestion_smoke($documentId, $docNumber, $suggestionTitle);
    Db::name('documents')->insert([
        'id' => $documentId,
        'company_id' => (string)Config::get('qms.company_id'),
        'category_id' => '00000000-0000-0000-0000-000000000052',
        'template_id' => null,
        'level' => 2,
        'doc_number' => $docNumber,
        'title' => $docTitle,
        'version' => 'A/0',
        'revision' => 0,
        'department_id' => null,
        'effective_date' => null,
        'review_date' => null,
        'status' => 'published',
        'file_path' => $sourcePath,
        'file_name' => basename($sourcePath),
        'file_type' => 'docx',
        'publish' => 1,
        'soft_delete' => 0,
        'record_status' => 1,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('qms_structured_documents')->insert([
        'id' => $structuredId,
        'company_id' => (string)Config::get('qms.company_id'),
        'source_asset_id' => null,
        'document_id' => $documentId,
        'document_role' => 'procedure',
        'doc_number' => $docNumber,
        'title' => $docTitle,
        'version' => 'A/0',
        'source_status' => 'current',
        'status' => 'structured',
        'markdown_path' => '',
        'rendered_file_path' => '',
        'render_status' => 'not_rendered',
        'review_note' => 'smoke',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('qms_document_blocks')->insert([
        'id' => $blockId,
        'company_id' => (string)Config::get('qms.company_id'),
        'structured_document_id' => $structuredId,
        'document_id' => $documentId,
        'stable_key' => 'procedure:record_suggest_smoke:purpose',
        'section_number' => '',
        'title' => '目的',
        'block_type' => 'purpose',
        'markdown' => "### 目的\n\n用于验证程序记录要求缺口进入智能体建议。\n",
        'sort_order' => 100,
        'source_locator' => $sourcePath,
        'status' => 'effective',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);

    $formalBlockLinksBefore = Db::name('qms_document_block_links')->count();
    $recordFormsBefore = Db::name('record_form_templates')->count();

    $summary = QmsElementService::refreshAgentSuggestions();
    assert_true(
        (int)($summary['procedure_record_gap_suggestions'] ?? 0) >= 1,
        'Agent refresh creates procedure record requirement gap suggestions'
    );

    $suggestion = Db::name('qms_agent_suggestions')
        ->where('title', $suggestionTitle)
        ->where('suggestion_type', 'document')
        ->where('status', 'open')
        ->find();
    assert_true(is_array($suggestion), 'Open document suggestion is created for procedure record requirement gap');
    assert_true(($suggestion['element_id'] ?? null) === null, 'Procedure record suggestion is not bound to a numbered element');
    assert_contains('未识别记录要求块', (string)$suggestion['content'], 'Suggestion content explains the missing record requirement block');
    assert_contains('/planning/structures/view?id=' . $structuredId, (string)$suggestion['evidence'], 'Suggestion evidence links to structured procedure');
    assert_contains('智能体只记录建议/缺口，不自动修改正式体系数据', (string)$suggestion['evidence'], 'Suggestion evidence preserves agent boundary');
    assert_true(
        Db::name('qms_document_block_links')->count() === $formalBlockLinksBefore,
        'Refreshing suggestions does not mutate formal document block links'
    );
    assert_true(
        Db::name('record_form_templates')->count() === $recordFormsBefore,
        'Refreshing suggestions does not mutate record form templates'
    );

    QmsElementService::reviewAgentSuggestion((string)$suggestion['id'], 'accepted', 'smoke accepted');
    QmsElementService::refreshAgentSuggestions();
    $openReviewedSuggestion = Db::name('qms_agent_suggestions')
        ->where('title', $suggestionTitle)
        ->where('suggestion_type', 'document')
        ->where('status', 'open')
        ->find();
    assert_true(!$openReviewedSuggestion, 'Accepted procedure record suggestion is not reopened automatically');

    $dashboardSource = file_get_contents(dirname(__DIR__) . '/app/controller/PlanningDashboard.php') ?: '';
    assert_contains('openProcedureRecordSuggestions', $dashboardSource, 'Dashboard loads procedure record requirement suggestions');

    $viewSource = file_get_contents(dirname(__DIR__) . '/app/view/planning_dashboard/index.html') ?: '';
    assert_contains('程序记录要求建议', $viewSource, 'Dashboard shows procedure record requirement suggestions');
    assert_contains('采纳建议', $viewSource, 'Dashboard allows accepting document suggestions');
    assert_contains('驳回建议', $viewSource, 'Dashboard allows rejecting document suggestions');
} finally {
    cleanup_procedure_record_suggestion_smoke($documentId, $docNumber, $suggestionTitle);
}

echo "qms_procedure_record_requirement_suggestion_smoke passed\n";
