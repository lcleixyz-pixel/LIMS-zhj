<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\model\Document;
use app\service\ControlledPrintService;
use app\service\DocumentControlService;
use app\service\TrialModeService;
use think\facade\Config;
use think\facade\Db;
use think\facade\Session;

if (
    !str_contains((string)Config::get('qms.environment_label', ''), '8011')
    && getenv('QMS_ALLOW_DESTRUCTIVE_SMOKE') !== '1'
) {
    fwrite(STDERR, "拒绝运行：本测试会创建临时文件记录，只能在 8011 候选环境执行。\n");
    exit(2);
}

function td_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }

    fwrite(STDOUT, "[PASS] {$message}\n");
}

function td_print_throws(Document $document, string $expectedMessage): bool
{
    try {
        ControlledPrintService::createLog($document, 1, 'G-R14 runtime smoke', '127.0.0.1');
    } catch (RuntimeException $exception) {
        return str_contains($exception->getMessage(), $expectedMessage);
    }

    return false;
}

function td_set_trial_mode(bool $enabled): void
{
    $qms = (array)Config::get('qms', []);
    $qms['trial_mode'] = (array)($qms['trial_mode'] ?? []);
    $qms['trial_mode']['enabled'] = $enabled;
    Config::set($qms, 'qms');
}

$companyId = (string)Config::get('qms.company_id');
$trialId = 'gr14-trial-doc-runtime-smoke';
$formalId = 'gr14-formal-doc-runtime-smoke';
$userId = (string)(Db::name('users')->where('publish', 1)->where('soft_delete', 0)->value('id') ?: '');
$now = date('Y-m-d H:i:s');

td_assert($userId !== '', 'TDR01 存在可用于打印留痕的有效用户');

td_set_trial_mode(true);
td_assert(
    TrialModeService::isSimulationTemplate(['status' => 'published']),
    'TDR01A 试运行模式下从正式模板新建的记录仍强制标记为模拟记录'
);
td_set_trial_mode(false);
td_assert(
    !TrialModeService::isSimulationTemplate(['status' => 'published']),
    'TDR01B 关闭试运行模式后正式模板恢复为正式记录来源'
);

Db::name('controlled_print_logs')->whereIn('document_id', [$trialId, $formalId])->delete();
Db::name('document_reviews')->whereIn('document_id', [$trialId, $formalId])->delete();
Db::name('document_distributions')->whereIn('document_id', [$trialId, $formalId])->delete();
Db::name('documents')->whereIn('id', [$trialId, $formalId])->delete();

try {
    Db::name('documents')->insertAll([
        [
            'id' => $trialId,
            'company_id' => $companyId,
            'level' => 2,
            'doc_number' => 'SIM-QP-GR14-RUNTIME',
            'title' => 'G-R14 试运行打印边界测试',
            'version' => 'TRIAL/0.1',
            'revision' => 0,
            'status' => 'trial_ready',
            'publish' => 1,
            'soft_delete' => 0,
            'record_status' => 1,
            'created' => $now,
            'modified' => $now,
        ],
        [
            'id' => $formalId,
            'company_id' => $companyId,
            'level' => 2,
            'doc_number' => 'QP-GR14-RUNTIME',
            'title' => 'G-R14 正式打印边界测试',
            'version' => 'A/0',
            'revision' => 0,
            'status' => 'published',
            'publish' => 1,
            'soft_delete' => 0,
            'record_status' => 1,
            'created' => $now,
            'modified' => $now,
        ],
    ]);
    Session::set('user.id', $userId);

    td_set_trial_mode(true);
    $formal = Document::find($formalId);
    td_assert(
        (function () use ($formal): bool {
            try {
                TrialModeService::assertDocumentApprovalAllowed($formal);
            } catch (\DomainException $exception) {
                return str_contains($exception->getMessage(), '禁止批准或发布非 SIM 正式文件');
            }

            return false;
        })(),
        'TDR01C 8011 后端拒绝批准非 SIM 正式文件'
    );
    td_assert(
        DocumentControlService::recordReview(
            $formalId,
            'obsolete',
            '试运行不得作废正式文件',
            null,
            $userId
        ) === null,
        'TDR01D 8011 后端拒绝评审或作废非 SIM 正式文件'
    );
    td_assert(
        DocumentControlService::distribute($formalId, [$userId]) === 0,
        'TDR01E 8011 后端拒绝新增分发非 SIM 正式文件'
    );
    $trial = Document::find($trialId);
    TrialModeService::assertDocumentApprovalAllowed($trial);
    $trialLog = ControlledPrintService::createLog($trial, 1, '受控试运行', '127.0.0.1');
    td_assert(
        str_starts_with((string)$trialLog->watermark_text, '试运行/非正式受控副本 '),
        'TDR02 trial_ready SIM 文件只能生成试运行水印'
    );

    td_set_trial_mode(false);
    td_assert(
        td_print_throws($trial, '试运行文件只能在受控试运行环境打印'),
        'TDR03 关闭试运行开关后 SIM 文件被服务端拒绝'
    );

    td_set_trial_mode(true);
    $trial->status = 'published';
    td_assert(
        td_print_throws($trial, '试运行文件状态无效'),
        'TDR04 SIM 文件误写为 published 时不得生成正式打印'
    );

    $formalLog = ControlledPrintService::createLog($formal, 1, '正式受控打印', '127.0.0.1');
    td_assert(
        str_starts_with((string)$formalLog->watermark_text, '受控打印 '),
        'TDR05 非 SIM 的 published 文件保持正式打印能力'
    );
} finally {
    Db::name('controlled_print_logs')->whereIn('document_id', [$trialId, $formalId])->delete();
    Db::name('document_reviews')->whereIn('document_id', [$trialId, $formalId])->delete();
    Db::name('document_distributions')->whereIn('document_id', [$trialId, $formalId])->delete();
    Db::name('documents')->whereIn('id', [$trialId, $formalId])->delete();
}

echo "qms_gr14_trial_document_runtime_smoke passed\n";
