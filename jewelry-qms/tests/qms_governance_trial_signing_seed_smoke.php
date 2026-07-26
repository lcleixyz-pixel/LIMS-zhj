<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
require $root . '/app/common.php';

$app = new think\App();
$app->initialize();

use think\facade\Db;

function signing_seed_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$script = $root . '/scripts/governance_trial_signing_seed.php';
signing_seed_assert(is_file($script), 'missing governance trial signing seed script');

$passwords = [
    'SIM_PREPARER_PASSWORD' => 'P@' . bin2hex(random_bytes(10)),
    'SIM_REVIEWER_PASSWORD' => 'R@' . bin2hex(random_bytes(10)),
    'SIM_APPROVER_PASSWORD' => 'A@' . bin2hex(random_bytes(10)),
];
$prefix = '';
foreach ($passwords as $key => $value) {
    $prefix .= $key . '=' . escapeshellarg($value) . ' ';
}
$command = $prefix . 'php ' . escapeshellarg($script) . ' 2>&1';

exec($command, $firstOutput, $firstCode);
signing_seed_assert($firstCode === 0, 'first seed failed: ' . implode("\n", $firstOutput));

$countsAfterFirst = [
    'users' => (int)Db::name('users')->whereIn('username', ['sim_preparer', 'sim_reviewer', 'sim_approver'])
        ->where('soft_delete', 0)->count(),
    'appointments' => (int)Db::name('employee_appointments')
        ->whereIn('appointment_key', [
            'SIM-8021-DOCUMENT-PREPARER',
            'SIM-8021-DOCUMENT-REVIEWER',
            'SIM-8021-DOCUMENT-APPROVER',
        ])->where('soft_delete', 0)->count(),
    'documents' => (int)Db::name('documents')->where('doc_number', 'SIM-SIGN-20260724')
        ->where('status', '<>', 'obsolete')
        ->where('soft_delete', 0)->count(),
];
signing_seed_assert($countsAfterFirst === [
    'users' => 3,
    'appointments' => 3,
    'documents' => 1,
], 'seeded SIM account, appointment or current document counts are wrong');

exec($command, $secondOutput, $secondCode);
signing_seed_assert($secondCode === 0, 'second seed failed: ' . implode("\n", $secondOutput));

$countsAfterSecond = [
    'users' => (int)Db::name('users')->whereIn('username', ['sim_preparer', 'sim_reviewer', 'sim_approver'])
        ->where('soft_delete', 0)->count(),
    'appointments' => (int)Db::name('employee_appointments')
        ->whereIn('appointment_key', [
            'SIM-8021-DOCUMENT-PREPARER',
            'SIM-8021-DOCUMENT-REVIEWER',
            'SIM-8021-DOCUMENT-APPROVER',
        ])->where('soft_delete', 0)->count(),
    'documents' => (int)Db::name('documents')->where('doc_number', 'SIM-SIGN-20260724')
        ->where('status', '<>', 'obsolete')
        ->where('soft_delete', 0)->count(),
];
signing_seed_assert($countsAfterSecond === $countsAfterFirst, 'seed must be idempotent');

$positionCodes = Db::name('employee_appointments')->alias('ea')
    ->join('qms_positions p', 'p.id = ea.position_id')
    ->whereIn('ea.appointment_key', [
        'SIM-8021-DOCUMENT-PREPARER',
        'SIM-8021-DOCUMENT-REVIEWER',
        'SIM-8021-DOCUMENT-APPROVER',
    ])
    ->order('ea.appointment_key', 'asc')
    ->column('p.code');
sort($positionCodes);
signing_seed_assert(
    $positionCodes === ['document_controller', 'technical_manager', 'top_management'],
    'SIM business positions are wrong: ' . implode(',', $positionCodes)
);

$documentId = (string)Db::name('documents')->where('doc_number', 'SIM-SIGN-20260724')
    ->where('status', '<>', 'obsolete')
    ->where('soft_delete', 0)
    ->order('modified', 'desc')
    ->value('id');
$sampleFilePath = (string)Db::name('documents')->where('id', $documentId)->value('file_path');
signing_seed_assert(
    $sampleFilePath !== '' && is_file($sampleFilePath),
    'SIM sample must reference the same local PDF used by the signing template'
);
$currentRound = (int)Db::name('approvals')->where('model_name', 'Document')->where('record', $documentId)
    ->max('workflow_round');
signing_seed_assert(
    (int)Db::name('approvals')->where('model_name', 'Document')->where('record', $documentId)
        ->where('record_status', 1)->where('workflow_round', $currentRound)->count() === 3,
    'SIM sample must preserve one complete active three-level workflow after trial use'
);

$blockedCommand = 'QMS_TRIAL_MODE=0 ' . $prefix . 'php ' . escapeshellarg($script) . ' 2>&1';
exec($blockedCommand, $blockedOutput, $blockedCode);
signing_seed_assert($blockedCode !== 0, 'seed must refuse when QMS_TRIAL_MODE is disabled');

echo "qms_governance_trial_signing_seed_smoke passed\n";
