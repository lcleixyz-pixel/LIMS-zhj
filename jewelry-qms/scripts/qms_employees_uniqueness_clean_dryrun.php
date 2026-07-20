#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * employees 唯一性清洗 dry-run v0.2（默认不写库）。
 *
 * 变更（相对 v0.1）：
 * - 扫描全表（含 soft_delete=1）
 * - 「可上索引」按 active-only 方案：仅 soft_delete=0 的非空值互斥；
 *   软删行与在职可同号；空串仍须归一 NULL（在职空串会撞 UNIQUE）
 *
 * 用法：
 *   php scripts/qms_employees_uniqueness_clean_dryrun.php
 *   php scripts/qms_employees_uniqueness_clean_dryrun.php --json=... --md=...
 *   QMS_EMPLOYEE_CLEAN_APPLY=1 php scripts/qms_employees_uniqueness_clean_dryrun.php --apply
 */

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
require $root . '/app/common.php';

$app = new think\App();
$app->initialize();

use think\facade\Db;

$args = array_slice($argv, 1);
$wantJson = null;
$wantMd = null;
$wantApply = false;
foreach ($args as $arg) {
    if (str_starts_with($arg, '--json=')) {
        $wantJson = substr($arg, 7);
    } elseif (str_starts_with($arg, '--md=')) {
        $wantMd = substr($arg, 5);
    } elseif ($arg === '--apply') {
        $wantApply = true;
    }
}

function norm(?string $v): string
{
    return trim((string)$v);
}

$rows = Db::name('employees')
    ->field('id,employee_number,email,name,publish,soft_delete,created,modified')
    ->order('soft_delete', 'asc')
    ->order('employee_number', 'asc')
    ->order('id', 'asc')
    ->select()
    ->toArray();

$activeByNumber = [];
$activeByEmail = [];
$allByNumber = [];
$allByEmail = [];
$blankNumberActive = [];
$blankEmailActive = [];
$blankNumberAll = [];
$blankEmailAll = [];

foreach ($rows as $row) {
    $soft = (int)($row['soft_delete'] ?? 0);
    $num = norm($row['employee_number'] ?? null);
    $email = norm($row['email'] ?? null);
    $active = $soft === 0;

    if ($num === '') {
        $blankNumberAll[] = $row;
        if ($active) {
            $blankNumberActive[] = $row;
        }
    } else {
        $allByNumber[$num][] = $row;
        if ($active) {
            $activeByNumber[$num][] = $row;
        }
    }

    if ($email === '') {
        $blankEmailAll[] = $row;
        if ($active) {
            $blankEmailActive[] = $row;
        }
    } else {
        $key = strtolower($email);
        $allByEmail[$key][] = $row;
        if ($active) {
            $activeByEmail[$key][] = $row;
        }
    }
}

$dupActiveNumbers = array_filter($activeByNumber, static fn(array $g): bool => count($g) > 1);
$dupActiveEmails = array_filter($activeByEmail, static fn(array $g): bool => count($g) > 1);
$dupAllNumbers = array_filter($allByNumber, static fn(array $g): bool => count($g) > 1);
$dupAllEmails = array_filter($allByEmail, static fn(array $g): bool => count($g) > 1);

$blocking = count($dupActiveNumbers) + count($dupActiveEmails) + (count($blankNumberActive) > 1 ? 1 : 0) + (count($blankEmailActive) > 1 ? 1 : 0);
// even a single blank among active blocks UNIQUE on '' ; treat any active blank as blocking for index
if (count($blankNumberActive) >= 1) {
    $blocking = max($blocking, count($dupActiveNumbers) + count($dupActiveEmails) + 1 + (count($blankEmailActive) >= 1 ? 1 : 0));
}
if (count($blankEmailActive) >= 1) {
    $blocking = count($dupActiveNumbers) + count($dupActiveEmails)
        + (count($blankNumberActive) >= 1 ? 1 : 0)
        + 1;
}

$report = [
    'version' => 'v0.2',
    'generated_at' => date('c'),
    'mode' => 'dry-run',
    'database_write_performed' => 0,
    'scan_scope' => 'all_rows_including_soft_deleted',
    'index_scheme' => 'active_only_generated_column',
    'total_employees' => count($rows),
    'active_employees' => count(array_filter($rows, static fn(array $r): bool => (int)($r['soft_delete'] ?? 0) === 0)),
    'soft_deleted_employees' => count(array_filter($rows, static fn(array $r): bool => (int)($r['soft_delete'] ?? 0) !== 0)),
    'blank_employee_number_active' => count($blankNumberActive),
    'blank_email_active' => count($blankEmailActive),
    'blank_employee_number_all' => count($blankNumberAll),
    'blank_email_all' => count($blankEmailAll),
    'duplicate_employee_number_groups_active' => count($dupActiveNumbers),
    'duplicate_email_groups_active' => count($dupActiveEmails),
    'duplicate_employee_number_groups_all' => count($dupAllNumbers),
    'duplicate_email_groups_all' => count($dupAllEmails),
    'duplicate_employee_numbers_active' => [],
    'duplicate_emails_active' => [],
    'duplicate_employee_numbers_all' => [],
    'duplicate_emails_all' => [],
    'blank_emails_active' => array_map(static fn(array $r): array => [
        'id' => (string)$r['id'],
        'name' => (string)($r['name'] ?? ''),
        'employee_number' => (string)($r['employee_number'] ?? ''),
        'soft_delete' => (int)($r['soft_delete'] ?? 0),
    ], $blankEmailActive),
    'proposed_actions' => [
        [
            'kind' => 'normalize_blank_to_null',
            'fields' => ['employee_number', 'email'],
            'scope' => 'preferably_active_rows_with_empty_string',
            'note' => '空串归一 NULL 步骤保留；上 active-only UNIQUE 前必须执行',
        ],
    ],
    'ready_for_unique_index' => 'no',
    'blocking_items' => 0,
];

$rowMap = static function (array $r): array {
    return [
        'id' => (string)$r['id'],
        'name' => (string)($r['name'] ?? ''),
        'employee_number' => (string)($r['employee_number'] ?? ''),
        'email' => (string)($r['email'] ?? ''),
        'soft_delete' => (int)($r['soft_delete'] ?? 0),
        'publish' => (int)($r['publish'] ?? 0),
    ];
};

foreach ($dupActiveNumbers as $key => $group) {
    $report['duplicate_employee_numbers_active'][] = [
        'employee_number' => $key,
        'count' => count($group),
        'rows' => array_map($rowMap, $group),
    ];
    $report['proposed_actions'][] = [
        'kind' => 'duplicate_employee_number_active',
        'key' => $key,
        'action' => 'manual_merge_or_renumber',
    ];
}
foreach ($dupActiveEmails as $key => $group) {
    $report['duplicate_emails_active'][] = [
        'email' => $key,
        'count' => count($group),
        'rows' => array_map($rowMap, $group),
    ];
    $report['proposed_actions'][] = [
        'kind' => 'duplicate_email_active',
        'key' => $key,
        'action' => 'manual_merge_or_reassign_email',
    ];
}
foreach ($dupAllNumbers as $key => $group) {
    $report['duplicate_employee_numbers_all'][] = [
        'employee_number' => $key,
        'count' => count($group),
        'rows' => array_map($rowMap, $group),
    ];
}
foreach ($dupAllEmails as $key => $group) {
    $report['duplicate_emails_all'][] = [
        'email' => $key,
        'count' => count($group),
        'rows' => array_map($rowMap, $group),
    ];
}

if (count($blankEmailActive) > 0) {
    $report['proposed_actions'][] = [
        'kind' => 'blank_email_active',
        'count' => count($blankEmailActive),
        'action' => 'normalize_empty_string_to_null',
    ];
}
if (count($blankNumberActive) > 0) {
    $report['proposed_actions'][] = [
        'kind' => 'blank_employee_number_active',
        'count' => count($blankNumberActive),
        'action' => 'normalize_empty_string_to_null',
    ];
}

$report['blocking_items'] = count($dupActiveNumbers) + count($dupActiveEmails)
    + (count($blankNumberActive) > 0 ? 1 : 0)
    + (count($blankEmailActive) > 0 ? 1 : 0);
$report['ready_for_unique_index'] = $report['blocking_items'] === 0 ? 'yes' : 'no';

if ($wantApply) {
    if (getenv('QMS_EMPLOYEE_CLEAN_APPLY') !== '1') {
        fwrite(STDERR, "REFUSED: --apply requires QMS_EMPLOYEE_CLEAN_APPLY=1\n");
        exit(2);
    }
    if (count($dupActiveNumbers) > 0 || count($dupActiveEmails) > 0) {
        fwrite(STDERR, "REFUSED: active non-empty duplicates exist; resolve manually before --apply\n");
        exit(3);
    }
    $n1 = Db::name('employees')->where('employee_number', '')->update(['employee_number' => null]);
    $n2 = Db::name('employees')->where('email', '')->update(['email' => null]);
    $report['mode'] = 'apply-normalize-blanks';
    $report['database_write_performed'] = 1;
    $report['normalized_blank_employee_number_rows'] = (int)$n1;
    $report['normalized_blank_email_rows'] = (int)$n2;
    $report['ready_for_unique_index'] = 'yes';
    $report['blocking_items'] = 0;
}

$md = [];
$md[] = '# employees 唯一性清洗 dry-run 报告 v0.2';
$md[] = '';
$md[] = '- 生成时间：' . $report['generated_at'];
$md[] = '- 模式：' . $report['mode'];
$md[] = '- 写库：' . ($report['database_write_performed'] ? '是' : '否');
$md[] = '- 扫描范围：全表（含软删）';
$md[] = '- 索引方案：active-only 生成列 UNIQUE';
$md[] = '- 员工总数：' . $report['total_employees'] . '（在职 ' . $report['active_employees'] . ' / 软删 ' . $report['soft_deleted_employees'] . '）';
$md[] = '- 在职空编号：' . $report['blank_employee_number_active'] . '；在职空邮箱：' . $report['blank_email_active'];
$md[] = '- 全表空编号：' . $report['blank_employee_number_all'] . '；全表空邮箱：' . $report['blank_email_all'];
$md[] = '- 在职重复编号组：' . $report['duplicate_employee_number_groups_active'];
$md[] = '- 在职重复邮箱组：' . $report['duplicate_email_groups_active'];
$md[] = '- 全表重复编号组（含跨软删同号，仅信息）：' . $report['duplicate_employee_number_groups_all'];
$md[] = '- 全表重复邮箱组（仅信息）：' . $report['duplicate_email_groups_all'];
$md[] = '- **可上索引（active-only）：' . $report['ready_for_unique_index'] . '**';
$md[] = '- 阻断项：' . $report['blocking_items'];
$md[] = '';
$md[] = '## 在职重复编号';
$md[] = $report['duplicate_employee_numbers_active'] === [] ? '（无）' : json_encode($report['duplicate_employee_numbers_active'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$md[] = '';
$md[] = '## 在职重复邮箱';
$md[] = $report['duplicate_emails_active'] === [] ? '（无）' : json_encode($report['duplicate_emails_active'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$md[] = '';
$md[] = '## 在职空邮箱（须归一 NULL）';
$md[] = $report['blank_emails_active'] === [] ? '（无）' : json_encode($report['blank_emails_active'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$md[] = '';
$md[] = '## 建议动作';
foreach ($report['proposed_actions'] as $a) {
    $md[] = '- ' . json_encode($a, JSON_UNESCAPED_UNICODE);
}
$md[] = '';

$markdown = implode("\n", $md);
echo $markdown;

if ($wantMd) {
    $dir = dirname($wantMd);
    if ($dir !== '' && $dir !== '.' && !is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents($wantMd, $markdown);
    echo "\nMarkdown written: {$wantMd}\n";
}
if ($wantJson) {
    $dir = dirname($wantJson);
    if ($dir !== '' && $dir !== '.' && !is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents($wantJson, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
    echo "JSON written: {$wantJson}\n";
}

exit($report['ready_for_unique_index'] === 'yes' ? 0 : 1);
