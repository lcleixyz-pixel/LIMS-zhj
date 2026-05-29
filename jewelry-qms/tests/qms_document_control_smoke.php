<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\DocumentControlService;
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

function ensure_document_control_tables(): void
{
    Db::execute(
        "CREATE TABLE IF NOT EXISTS `document_distributions` (
          `id` varchar(36) NOT NULL,
          `company_id` varchar(36) NOT NULL,
          `document_id` varchar(36) NOT NULL,
          `user_id` varchar(36) NOT NULL,
          `site_id` varchar(36) DEFAULT NULL,
          `distributed_at` datetime NOT NULL,
          `confirmed_at` datetime DEFAULT NULL,
          `recalled_at` datetime DEFAULT NULL,
          `remarks` text,
          `publish` tinyint(1) DEFAULT 1,
          `soft_delete` tinyint(1) DEFAULT 0,
          `created` datetime DEFAULT NULL,
          `created_by` varchar(36) DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `document_id` (`document_id`),
          KEY `user_id` (`user_id`),
          KEY `site_id` (`site_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    Db::execute(
        "CREATE TABLE IF NOT EXISTS `document_reviews` (
          `id` varchar(36) NOT NULL,
          `company_id` varchar(36) NOT NULL,
          `document_id` varchar(36) NOT NULL,
          `review_date` date NOT NULL,
          `result` enum('continue','revise','obsolete') NOT NULL,
          `review_note` text NOT NULL,
          `next_review_date` date DEFAULT NULL,
          `reviewed_by` varchar(36) DEFAULT NULL,
          `publish` tinyint(1) DEFAULT 1,
          `soft_delete` tinyint(1) DEFAULT 0,
          `created` datetime DEFAULT NULL,
          `created_by` varchar(36) DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `document_id` (`document_id`),
          KEY `review_date` (`review_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function cleanup_document_control_smoke(string $documentId, array $userIds): void
{
    Db::name('document_distributions')->where('document_id', $documentId)->delete();
    Db::name('document_reviews')->where('document_id', $documentId)->delete();
    $notificationIds = Db::name('notifications')
        ->where('link_controller', 'document')
        ->where('link_id', $documentId)
        ->column('id');
    if ($notificationIds !== []) {
        Db::name('notification_users')->whereIn('notification_id', $notificationIds)->delete();
        Db::name('notifications')->whereIn('id', $notificationIds)->delete();
    }
    Db::name('documents')->where('id', $documentId)->delete();
    Db::name('users')->whereIn('id', $userIds)->delete();
}

$root = dirname(__DIR__);
$schema = (string)file_get_contents($root . '/database/jewelry_qms.sql');
$migration = (string)file_get_contents($root . '/database/migrations/20260529_document_control.sql');
$route = (string)file_get_contents($root . '/route/app.php');
$rbac = (string)file_get_contents($root . '/app/middleware/Rbac.php');
$documentController = (string)file_get_contents($root . '/app/controller/Document.php');
$documentView = (string)file_get_contents($root . '/app/view/document/view.html');

foreach ([$schema, $migration] as $source) {
    assert_contains('CREATE TABLE', $source, 'Document control schema declares tables');
    assert_contains('document_distributions', $source, 'Document control schema includes document_distributions');
    assert_contains('document_reviews', $source, 'Document control schema includes document_reviews');
    assert_contains("enum('continue','revise','obsolete')", $source, 'Document reviews store controlled outcomes');
}

foreach ([
    'document/distribute',
    'document/confirmReceipt',
    'document/confirmRecall',
    'document/review',
    'document/obsolete',
] as $path) {
    assert_contains($path, $route, 'Route exposes ' . $path);
}

assert_true(class_exists(DocumentControlService::class), 'DocumentControlService exists');
foreach (['distribute', 'confirmreceipt', 'confirmrecall', 'review'] as $action) {
    assert_contains($action, $rbac, 'Document control write action is RBAC-covered: ' . $action);
}
assert_contains('DocumentControlService', $documentController, 'Document controller delegates control operations');
assert_contains('分发记录', $documentView, 'Document detail shows distribution panel');
assert_contains('评审记录', $documentView, 'Document detail shows review panel');
assert_contains('确认回收', $documentView, 'Document detail supports recall confirmation');

ensure_document_control_tables();

$companyId = (string)Config::get('qms.company_id');
$documentId = 'doc-control-smoke-doc';
$userA = 'doc-control-smoke-user-a';
$userB = 'doc-control-smoke-user-b';
$now = date('Y-m-d H:i:s');

try {
    cleanup_document_control_smoke($documentId, [$userA, $userB]);
    Db::name('users')->insertAll([
        [
            'id' => $userA,
            'company_id' => $companyId,
            'username' => 'doc_control_a',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'name' => '文件分发A',
            'role' => 'staff',
            'publish' => 1,
            'soft_delete' => 0,
            'created' => $now,
            'modified' => $now,
        ],
        [
            'id' => $userB,
            'company_id' => $companyId,
            'username' => 'doc_control_b',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'name' => '文件分发B',
            'role' => 'staff',
            'publish' => 1,
            'soft_delete' => 0,
            'created' => $now,
            'modified' => $now,
        ],
    ]);
    Db::name('documents')->insert([
        'id' => $documentId,
        'company_id' => $companyId,
        'level' => 2,
        'doc_number' => 'QP-DOC-CONTROL-SMOKE',
        'title' => '文件控制增强冒烟文件',
        'version' => 'A/0',
        'revision' => 0,
        'status' => 'published',
        'review_date' => date('Y-m-d', strtotime('+30 days')),
        'publish' => 1,
        'soft_delete' => 0,
        'record_status' => 1,
        'created' => $now,
        'modified' => $now,
    ]);

    $created = DocumentControlService::distribute($documentId, [$userA, $userB], null, 'smoke distribution');
    assert_true($created === 2, 'Initial distribution creates two rows');
    $createdAgain = DocumentControlService::distribute($documentId, [$userA], null, 'duplicate ignored');
    assert_true($createdAgain === 0, 'Duplicate distribution is ignored');

    $distributionA = Db::name('document_distributions')
        ->where('document_id', $documentId)
        ->where('user_id', $userA)
        ->find();
    assert_true(is_array($distributionA), 'Distribution row exists for user A');
    DocumentControlService::confirmReceipt((string)$distributionA['id'], $userA);
    $confirmedAt = Db::name('document_distributions')->where('id', $distributionA['id'])->value('confirmed_at');
    assert_true(!empty($confirmedAt), 'Receipt confirmation stores confirmed_at');

    DocumentControlService::recordReview($documentId, 'continue', '继续使用', '2026-12-31', $userA);
    $reviewDate = Db::name('documents')->where('id', $documentId)->value('review_date');
    assert_true((string)$reviewDate === '2026-12-31', 'Continue review updates next review date');

    DocumentControlService::recordReview($documentId, 'obsolete', '作废并回收', null, $userA);
    $status = Db::name('documents')->where('id', $documentId)->value('status');
    assert_true((string)$status === 'obsolete', 'Obsolete review updates document status');
    $notifiedUsers = Db::name('notifications')
        ->alias('n')
        ->join('notification_users nu', 'nu.notification_id = n.id')
        ->where('n.link_controller', 'document')
        ->where('n.link_id', $documentId)
        ->column('nu.user_id');
    assert_true(in_array($userA, $notifiedUsers, true) && in_array($userB, $notifiedUsers, true), 'Obsolete review notifies distributed users for recall');

    DocumentControlService::confirmRecall((string)$distributionA['id'], $userA);
    $recalledAt = Db::name('document_distributions')->where('id', $distributionA['id'])->value('recalled_at');
    assert_true(!empty($recalledAt), 'Recall confirmation stores recalled_at');
} finally {
    cleanup_document_control_smoke($documentId, [$userA, $userB]);
}

echo "qms_document_control_smoke passed\n";
