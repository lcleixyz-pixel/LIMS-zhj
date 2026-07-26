<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\QmsDocumentStructureService;
use think\facade\Db;

(new think\App())->initialize();

function record_structure_reactivation_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$templateId = qms_uuid();
$assetId = qms_uuid();
$structureId = qms_uuid();
$now = date('Y-m-d H:i:s');
$renderedPath = '';

Db::startTrans();
try {
    Db::name('record_form_templates')->insert([
        'id' => $templateId,
        'company_id' => '00000000-0000-0000-0000-000000000001',
        'doc_number' => 'SIM-TEST/BG-REACTIVATE',
        'name' => '软删除结构恢复测试表',
        'source_file_path' => 'record_form_schema/SIM-TEST_BG-REACTIVATE.json',
        'source_file_name' => 'SIM-TEST_BG-REACTIVATE.json',
        'print_template_key' => 'qms_record_generic_v1',
        'field_schema' => json_encode([
            ['key' => 'test_value', 'label' => '测试值', 'type' => 'text', 'required' => false],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'version' => 'TEST/0.1',
        'status' => 'draft',
        'review_status' => 'completed',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('qms_document_assets')->insert([
        'id' => $assetId,
        'company_id' => '00000000-0000-0000-0000-000000000001',
        'source_kind' => 'record_form',
        'record_form_template_id' => $templateId,
        'original_name' => 'SIM-TEST_BG-REACTIVATE.json',
        'original_path' => 'record_form_schema/SIM-TEST_BG-REACTIVATE.json',
        'normalized_name' => 'SIM-TEST_BG-REACTIVATE-TEST_0.1.json',
        'archived_path' => '',
        'file_type' => 'json',
        'archive_status' => 'missing',
        'review_status' => 'structured',
        'publish' => 0,
        'soft_delete' => 1,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('qms_structured_documents')->insert([
        'id' => $structureId,
        'company_id' => '00000000-0000-0000-0000-000000000001',
        'source_asset_id' => $assetId,
        'document_role' => 'record_form',
        'doc_number' => 'SIM-TEST/BG-REACTIVATE',
        'title' => '软删除结构恢复测试表',
        'version' => 'TEST/0.1',
        'source_status' => 'current',
        'render_status' => 'not_rendered',
        'status' => 'structured',
        'publish' => 0,
        'soft_delete' => 1,
        'created' => $now,
        'modified' => $now,
    ]);

    QmsDocumentStructureService::structureRecordFormTemplate($templateId);

    record_structure_reactivation_assert(
        (int)Db::name('qms_document_assets')->where('id', $assetId)->where('soft_delete', 0)->count() === 1,
        '定向结构化应复用并恢复软删除资产'
    );
    record_structure_reactivation_assert(
        (int)Db::name('qms_structured_documents')->where('id', $structureId)->where('soft_delete', 0)->count() === 1,
        '定向结构化应复用并恢复软删除结构化文件'
    );
    record_structure_reactivation_assert(
        (int)Db::name('qms_document_assets')->where('record_form_template_id', $templateId)->count() === 1,
        '恢复软删除资产时不得创建重复资产'
    );
    $renderedPath = (string)Db::name('qms_structured_documents')
        ->where('id', $structureId)
        ->value('rendered_file_path');
} finally {
    Db::rollback();
    if ($renderedPath !== '') {
        $absolutePath = dirname(__DIR__) . '/' . ltrim($renderedPath, '/');
        if (is_file($absolutePath)) {
            unlink($absolutePath);
        }
    }
}

echo "qms_record_form_structure_reactivation_runtime_smoke passed\n";
