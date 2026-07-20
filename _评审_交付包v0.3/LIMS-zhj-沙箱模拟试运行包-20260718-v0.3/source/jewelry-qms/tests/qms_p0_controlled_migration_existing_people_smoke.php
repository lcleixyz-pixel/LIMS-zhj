<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\P0ControlledMigrationPackageService;

$passes = [];
$failures = [];

function b7_case(bool $condition, string $id, string $message): void
{
    global $passes, $failures;
    if ($condition) {
        $passes[] = $id . ' ' . $message;
    } else {
        $failures[] = $id . ' ' . $message;
    }
}

function b7_remove_tree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
        $item = $path . DIRECTORY_SEPARATOR . $entry;
        is_dir($item) ? b7_remove_tree($item) : unlink($item);
    }
    rmdir($path);
}

$root = dirname(__DIR__);
$confirmation = json_decode(
    (string)file_get_contents($root . '/database/fixtures/g_r13_b7_local_trial_confirmation.json'),
    true,
    512,
    JSON_THROW_ON_ERROR
);
$output = sys_get_temp_dir() . '/qms-r13b7-existing-people';
b7_remove_tree($output);

try {
    $summary = P0ControlledMigrationPackageService::build($confirmation, $output, false);
    $manifest = json_decode((string)file_get_contents($output . '/00-manifest.json'), true);
    $diff = json_decode(
        (string)file_get_contents($output . '/evidence/migration-diff.expected.json'),
        true
    );
    $sql = (string)file_get_contents($output . '/sql/20-organization-migration.sql');
    $rollbackSql = (string)file_get_contents($output . '/sql/90-row-rollback.sql');

    b7_case(
        ($manifest['package_version'] ?? '') === 'g-r13-b7-local-controlled-migration-v0.3',
        'B700',
        '字符集自保护后迁移包升为 v0.3'
    );
    b7_case(($summary['local_apply_authorized'] ?? false) === true, 'B701', '仅授权本机试运行迁移');
    b7_case(($summary['cloud_apply_authorized'] ?? true) === false, 'B702', '不授权云端迁移');
    b7_case(
        ($manifest['requires_separate_b7_approval'] ?? true) === false,
        'B703',
        'B7 确认完成后不再等待同一道闸门'
    );
    b7_case(
        (int)($diff['allowed']['employees'] ?? -1) === 0,
        'B704',
        '复用 E007/E008，不新增人员'
    );
    b7_case(
        str_contains($sql, "'E007'") && str_contains($sql, "'E008'"),
        'B705',
        '迁移 SQL 使用现行正式员工编号'
    );
    b7_case(
        str_contains($sql, '米尔布拉·阿卜杜麦麦提')
        && str_contains($sql, '如则托合提·阿卜杜加帕尔'),
        'B706',
        '迁移证据保留人员全名'
    );
    b7_case(
        (int)($diff['allowed']['qms_positions_renamed'] ?? -1) === 2
        && str_contains($sql, "'document_controller'")
        && str_contains($sql, "'文件管理员'"),
        'B708',
        '迁移把两个旧岗位名称收敛到现行称谓'
    );
    b7_case(
        str_contains($rollbackSql, "'document_controller'")
        && str_contains($rollbackSql, "'资料管理员'")
        && str_contains($rollbackSql, "'company_general_manager'")
        && str_contains($rollbackSql, "'公司总经理'"),
        'B709',
        '行级回退恢复迁移前岗位名称'
    );
    $sqlFiles = glob($output . '/sql/*.sql') ?: [];
    b7_case(
        count($sqlFiles) === 6
        && count(array_filter(
            $sqlFiles,
            static fn (string $file): bool =>
                str_contains((string)file_get_contents($file), 'SET NAMES utf8mb4;')
        )) === 6,
        'B710',
        '六个 SQL 均声明 UTF-8 客户端字符集'
    );
} catch (Throwable $exception) {
    $failures[] = 'B701-B706 build failed: ' . $exception->getMessage();
}

$invalid = $confirmation;
$invalid['people']['hetian_document_controller']['formal_name'] = '如则托合提';
try {
    P0ControlledMigrationPackageService::build($invalid, $output . '-invalid', false);
    b7_case(false, 'B707', '编号与姓名不一致必须阻断');
} catch (DomainException) {
    b7_case(true, 'B707', '编号与姓名不一致必须阻断');
}

foreach ($passes as $pass) {
    fwrite(STDOUT, "PASS {$pass}\n");
}
foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL {$failure}\n");
}
if ($failures !== []) {
    fwrite(STDERR, sprintf(
        "qms_p0_controlled_migration_existing_people_smoke failed: %d passed, %d failed\n",
        count($passes),
        count($failures)
    ));
    exit(1);
}
fwrite(STDOUT, "qms_p0_controlled_migration_existing_people_smoke passed: B700-B710\n");
