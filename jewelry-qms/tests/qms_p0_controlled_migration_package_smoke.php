<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\P0ControlledMigrationPackageService;
use think\facade\Db;

if (!class_exists(P0ControlledMigrationPackageService::class)) {
    fwrite(STDERR, "P0ControlledMigrationPackageService missing\n");
    exit(1);
}

$passes = [];
$failures = [];

function b6_case(bool $condition, string $id, string $message): void
{
    global $passes, $failures;
    if ($condition) {
        $passes[] = $id . ' ' . $message;
    } else {
        $failures[] = $id . ' ' . $message;
    }
}

function b6_remove_tree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
        $item = $path . DIRECTORY_SEPARATOR . $entry;
        is_dir($item) ? b6_remove_tree($item) : unlink($item);
    }
    rmdir($path);
}

function b6_confirmation(): array
{
    $companyId = (string)Db::name('companies')->where('soft_delete', 0)->value('id');
    return [
        'schema_version' => 'g-r13-b6-confirmation-v0.1',
        'status' => 'approved',
        'target_database' => 'jewelry_qms_p0_r13b6',
        'company_id' => $companyId,
        'document_number' => 'ORG-APPOINT-2026-01',
        'effective_date' => '2026-07-17',
        'source_excerpt' => '经人工确认的人员、岗位、场所和授权关系，仅用于 B6 隔离迁移演练。',
        'people' => [
            'hetian_document_controller' => [
                'formal_name' => '如则托合提',
                'employee_number' => 'E012',
            ],
            'hetian_equipment_manager' => [
                'formal_name' => '米尔布拉',
                'employee_number' => 'E013',
            ],
        ],
        'reviews' => [
            'quality_manager' => ['name' => '张晓磊', 'decision' => 'approved', 'date' => '2026-07-17'],
            'technical_manager' => ['name' => '刘恒春', 'decision' => 'approved', 'date' => '2026-07-17'],
            'top_management' => ['name' => '俞炳星', 'decision' => 'approved', 'date' => '2026-07-17'],
        ],
        'rehearsal_marker' => 'B6_REHEARSAL_ONLY_NOT_REAL_APPROVAL',
    ];
}

$root = dirname(__DIR__);
$consoleSource = (string)file_get_contents($root . '/config/console.php');
b6_case(
    class_exists('app\\command\\P0BuildControlledMigrationPackage')
    && str_contains($consoleSource, 'P0BuildControlledMigrationPackage::class'),
    'E13',
    '控制台注册迁移包命令'
);
$template = json_decode(
    (string)file_get_contents($root . '/database/fixtures/g_r13_b6_confirmation.template.json'),
    true
);
$outputDir = sys_get_temp_dir() . '/qms-r13b6-package-smoke';
b6_remove_tree($outputDir);

try {
    P0ControlledMigrationPackageService::build((array)$template, $outputDir, true);
    b6_case(false, 'E01', 'pending 确认必须阻断');
} catch (DomainException $exception) {
    b6_case(str_contains($exception->getMessage(), 'status'), 'E01', 'pending 确认必须阻断');
}

$confirmation = b6_confirmation();
$summary = P0ControlledMigrationPackageService::build($confirmation, $outputDir, true);
$manifest = json_decode((string)file_get_contents($outputDir . '/00-manifest.json'), true);
$migrationSql = (string)file_get_contents($outputDir . '/sql/20-organization-migration.sql');
$allSql = implode("\n", array_map(
    static fn (string $file): string => (string)file_get_contents($file),
    glob($outputDir . '/sql/*.sql') ?: []
));

b6_case(($summary['production_apply_authorized'] ?? null) === false, 'E02', '演练包永不取得生产授权');
b6_case(count((array)($summary['appointment_keys'] ?? [])) === 25, 'E03', '目标任命固定为 25 项');
b6_case(
    str_contains($migrationSql, 'PLACE01')
    && str_contains($migrationSql, 'PLACE02')
    && !str_contains($migrationSql, "'MAIN'")
    && !str_contains($migrationSql, "'HETIAN'"),
    'E04',
    '迁移复用两个正式场所'
);
b6_case(
    !str_contains($migrationSql, "'刘恒春', 'quality_manager'"),
    'E05',
    '刘恒春不取得常设质量负责人'
);

$requiredErrorCount = 0;
foreach ([
    ['document_number'],
    ['effective_date'],
    ['source_excerpt'],
    ['people', 'hetian_document_controller', 'formal_name'],
    ['people', 'hetian_document_controller', 'employee_number'],
    ['people', 'hetian_equipment_manager', 'employee_number'],
] as $fieldPath) {
    $invalid = $confirmation;
    $cursor =& $invalid;
    foreach ($fieldPath as $segment) {
        $cursor =& $cursor[$segment];
    }
    $cursor = '';
    unset($cursor);
    try {
        P0ControlledMigrationPackageService::build($invalid, $outputDir . '-invalid', true);
    } catch (DomainException) {
        $requiredErrorCount++;
    }
}
b6_case($requiredErrorCount === 6, 'E06', '关键确认字段任一缺失均阻断');

$invalidReview = $confirmation;
$invalidReview['reviews']['technical_manager']['decision'] = 'pending';
try {
    P0ControlledMigrationPackageService::build($invalidReview, $outputDir . '-review', true);
    b6_case(false, 'E07', '三方复核必须全部批准');
} catch (DomainException) {
    b6_case(true, 'E07', '三方复核必须全部批准');
}

$wrongDatabase = $confirmation;
$wrongDatabase['target_database'] = 'jewelry_qms';
try {
    P0ControlledMigrationPackageService::build($wrongDatabase, $outputDir . '-database', true);
    b6_case(false, 'E08', '演练模式拒绝非隔离数据库');
} catch (DomainException) {
    b6_case(true, 'E08', '演练模式拒绝非隔离数据库');
}

$expectedSql = [
    '00-preflight-readonly.sql',
    '10-schema-integrity.sql',
    '20-organization-migration.sql',
    '30-postflight-readonly.sql',
    '90-row-rollback.sql',
    '91-schema-rollback-emergency-only.sql',
];
$actualSql = array_map('basename', glob($outputDir . '/sql/*.sql') ?: []);
sort($expectedSql);
sort($actualSql);
b6_case($actualSql === $expectedSql, 'E09', '受控包生成六个 SQL 文件');

$checksumLines = file($outputDir . '/SHA256SUMS.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
b6_case(
    is_array($manifest)
    && ($manifest['requires_separate_b7_approval'] ?? false) === true
    && trim((string)($manifest['git_commit'] ?? '')) !== ''
    && count($checksumLines) >= 10,
    'E10',
    'manifest 和 SHA256 清单完整'
);
b6_case(
    !str_contains($allSql, '$2y$')
    && !str_contains($allSql, 'customer_name')
    && !str_contains($allSql, 'B5 页面验收客户'),
    'E11',
    'SQL 不包含口令或测试业务记录'
);

$secondDir = $outputDir . '-second';
b6_remove_tree($secondDir);
$second = P0ControlledMigrationPackageService::build($confirmation, $secondDir, true);
b6_case(
    ($summary['semantic_sha256'] ?? '') !== ''
    && ($summary['semantic_sha256'] ?? '') === ($second['semantic_sha256'] ?? ''),
    'E12',
    '相同输入重复生成保持语义一致'
);

foreach ($passes as $pass) {
    fwrite(STDOUT, "PASS {$pass}\n");
}
foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL {$failure}\n");
}

b6_remove_tree($outputDir);
b6_remove_tree($secondDir);
foreach (glob($outputDir . '-*') ?: [] as $extraDir) {
    b6_remove_tree($extraDir);
}

if ($failures !== []) {
    fwrite(STDERR, sprintf(
        "qms_p0_controlled_migration_package_smoke failed: %d passed, %d failed\n",
        count($passes),
        count($failures)
    ));
    exit(1);
}

fwrite(STDOUT, "qms_p0_controlled_migration_package_smoke passed: E01-E13\n");
