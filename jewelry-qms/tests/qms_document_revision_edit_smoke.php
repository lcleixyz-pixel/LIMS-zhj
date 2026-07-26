<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\controller\Document as DocumentController;
use think\facade\Db;

$app = new think\App();
$app->initialize();

function revision_edit_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$companyId = (string)(Db::name('companies')->where('soft_delete', 0)->value('id') ?: '');
$suffix = strtoupper(substr(str_replace('-', '', qms_uuid()), 0, 10));
$parentId = qms_uuid();
$childId = qms_uuid();
$otherId = qms_uuid();
$revisionNumber = 'SIM-REVISION-EDIT-' . $suffix;
$otherNumber = 'SIM-UNRELATED-' . $suffix;
$now = date('Y-m-d H:i:s');

try {
    foreach ([
        [
            'id' => $parentId,
            'doc_number' => $revisionNumber,
            'version' => 'A/0',
            'revision' => 0,
            'status' => 'trial_ready',
            'supersedes_document_id' => null,
            'revision_root_id' => null,
        ],
        [
            'id' => $childId,
            'doc_number' => $revisionNumber,
            'version' => 'A/1',
            'revision' => 1,
            'status' => 'draft',
            'supersedes_document_id' => $parentId,
            'revision_root_id' => $parentId,
        ],
        [
            'id' => $otherId,
            'doc_number' => $otherNumber,
            'version' => 'A/0',
            'revision' => 0,
            'status' => 'draft',
            'supersedes_document_id' => null,
            'revision_root_id' => null,
        ],
    ] as $row) {
        Db::name('documents')->insert(array_merge($row, [
            'company_id' => $companyId,
            'level' => 1,
            'title' => 'SIM 修订编辑回归',
            'publish' => 0,
            'soft_delete' => 0,
            'created' => $now,
            'modified' => $now,
        ]));
    }

    $controller = new DocumentController($app);
    $method = new ReflectionMethod($controller, 'uniqueDocumentNumberRule');
    $method->setAccessible(true);
    $rule = $method->invoke($controller, $childId);

    revision_edit_assert(
        $rule($revisionNumber) === true,
        '编辑修订版时，同一修订世系的原版本不应触发编号重复'
    );
    revision_edit_assert(
        $rule($otherNumber) === '文件编号已存在',
        '编辑修订版时，其他文件的相同编号仍应被拒绝'
    );

    $editView = (string)file_get_contents(__DIR__ . '/../app/view/document/edit.html');
    revision_edit_assert(str_contains($editView, 'name="change_reason"'), '编辑页应提交修订说明字段');
    revision_edit_assert(str_contains($editView, '修订说明'), '编辑页应使用修订说明业务名称');
} finally {
    Db::name('documents')->whereIn('id', [$parentId, $childId, $otherId])->delete();
}

echo "qms_document_revision_edit_smoke passed\n";
