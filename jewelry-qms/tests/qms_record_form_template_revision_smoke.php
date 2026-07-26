<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\model\RecordFormTemplate;
use app\service\RecordFormTemplateRevisionService;
use think\facade\Db;

$app = new think\App();
$app->initialize();

function template_revision_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__);
$controllerSource = (string)file_get_contents($root . '/app/controller/RecordFormTemplate.php');
$viewSource = (string)file_get_contents($root . '/app/view/record_form_template/view.html');
$routeSource = (string)file_get_contents($root . '/route/app.php');

template_revision_assert(
    str_contains($routeSource, 'record_form_template/createRevision'),
    '记录模板应提供建立修订草稿路由'
);
template_revision_assert(
    str_contains($controllerSource, 'public function createRevision'),
    '记录模板控制器应提供建立修订草稿动作'
);
template_revision_assert(
    str_contains($viewSource, "\$record.status == 'draft' && qms_can_action"),
    '只有草稿模板详情页可以显示直接编辑入口'
);
template_revision_assert(
    str_contains($viewSource, '复制为修订草稿'),
    '非草稿模板详情页应提供清晰的换版入口'
);
template_revision_assert(
    str_contains($viewSource, '旧版本保持不变'),
    '换版入口应向使用者说明旧版保留边界'
);

template_revision_assert(
    RecordFormTemplateRevisionService::nextVersion('A/0') === 'A/1',
    'A/0 应递增为 A/1'
);
template_revision_assert(
    RecordFormTemplateRevisionService::nextVersion('GOV-TRIAL/0.1') === 'GOV-TRIAL/0.2',
    '治理试运行版本应递增末位数字'
);

$companyId = (string)(Db::name('companies')->where('soft_delete', 0)->value('id') ?: '');
$sourceId = qms_uuid();
$sourceLinkId = qms_uuid();
$sourceAssetId = qms_uuid();
$suffix = strtoupper(substr(str_replace('-', '', $sourceId), 0, 10));
$docNumber = 'SIM-TEMPLATE-REV-' . $suffix;
$now = date('Y-m-d H:i:s');
$createdTemplateIds = [$sourceId];

try {
    Db::name('record_form_templates')->insert([
        'id' => $sourceId,
        'company_id' => $companyId,
        'doc_number' => $docNumber,
        'canonical_doc_number' => 'XZTC/BG-TEST-REV',
        'name' => 'SIM 记录模板换版测试',
        'module' => '换版测试',
        'print_template_key' => 'generic_record_form',
        'field_schema' => '[{"key":"note","label":"说明","type":"text","required":false,"print_bind":"note"}]',
        'version' => 'GOV-TRIAL/0.1',
        'status' => 'trial_ready',
        'review_status' => 'completed',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('qms_document_block_links')->insert([
        'id' => $sourceLinkId,
        'company_id' => $companyId,
        'block_id' => qms_uuid(),
        'record_form_template_id' => $sourceId,
        'relation_type' => 'requires_record',
        'confidence' => 'high',
        'note' => '来源追溯测试',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);
    Db::name('qms_document_assets')->insert([
        'id' => $sourceAssetId,
        'company_id' => $companyId,
        'source_kind' => 'record_form',
        'record_form_template_id' => $sourceId,
        'original_name' => 'template-source.docx',
        'original_path' => '/tmp/template-source.docx',
        'normalized_name' => 'template-source.docx',
        'archive_status' => 'missing',
        'review_status' => 'published',
        'source_note' => '来源附件测试',
        'publish' => 1,
        'soft_delete' => 0,
        'created' => $now,
        'modified' => $now,
    ]);

    $source = RecordFormTemplate::where('id', $sourceId)->find();
    template_revision_assert($source !== null, '换版测试源模板应存在');

    $result = RecordFormTemplateRevisionService::createDraftRevision($source, '调整处置记录字段');
    /** @var RecordFormTemplate $draft */
    $draft = $result['template'];
    $createdTemplateIds[] = (string)$draft->id;

    template_revision_assert(empty($result['reused']), '首次换版应建立新草稿');
    template_revision_assert((string)$draft->id !== $sourceId, '修订草稿必须使用新 ID');
    template_revision_assert((string)$draft->status === 'draft', '修订版本必须从草稿状态开始');
    template_revision_assert((string)$draft->version === 'GOV-TRIAL/0.2', '修订草稿应递增版本');
    template_revision_assert((string)$draft->supersedes_template_id === $sourceId, '修订草稿应指向上一版本');
    template_revision_assert((string)$draft->revision_root_id === $sourceId, '首个修订草稿应以源模板作为版本根');
    template_revision_assert((string)$draft->revision_note === '调整处置记录字段', '修订说明应独立保存');
    template_revision_assert((string)$draft->company_id === $companyId, '修订草稿应继承机构边界');
    template_revision_assert((string)$draft->doc_number === $docNumber, '同一版本链应保持受控编号');
    template_revision_assert((string)$draft->field_schema === (string)$source->field_schema, '新草稿应复制原字段配置');
    template_revision_assert(
        (string)RecordFormTemplate::where('id', $sourceId)->value('status') === 'trial_ready',
        '建立修订草稿不得改变旧版状态'
    );
    template_revision_assert(
        (int)Db::name('qms_document_block_links')
            ->where('record_form_template_id', (string)$draft->id)
            ->where('note', '来源追溯测试')
            ->count() === 1,
        '修订草稿应复制程序要求追溯关系'
    );
    template_revision_assert(
        (int)Db::name('qms_document_assets')
            ->where('record_form_template_id', (string)$draft->id)
            ->where('source_note', '来源附件测试')
            ->count() === 1,
        '修订草稿应复制来源附件档案关系'
    );

    $repeat = RecordFormTemplateRevisionService::createDraftRevision($source, '再次点击不应重复创建');
    template_revision_assert(!empty($repeat['reused']), '同一旧版已有修订草稿时应复用');
    template_revision_assert((string)$repeat['template']->id === (string)$draft->id, '重复点击应返回现有修订草稿');
} finally {
    $newIds = array_values(array_filter($createdTemplateIds, static fn(string $id): bool => $id !== $sourceId));
    if ($newIds !== []) {
        Db::name('qms_document_block_links')->whereIn('record_form_template_id', $newIds)->delete();
        Db::name('qms_document_assets')->whereIn('record_form_template_id', $newIds)->delete();
    }
    Db::name('qms_document_block_links')->where('id', $sourceLinkId)->delete();
    Db::name('qms_document_assets')->where('id', $sourceAssetId)->delete();
    Db::name('record_form_templates')->whereIn('id', $createdTemplateIds)->delete();
}

echo "qms_record_form_template_revision_smoke passed\n";
