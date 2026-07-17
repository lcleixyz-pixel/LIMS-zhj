<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes = [];

function mr_source(string $path): string
{
    global $root;
    $file = $root . '/' . $path;

    return is_file($file) ? (string)file_get_contents($file) : '';
}

function mr_check(bool $condition, string $id, string $message): void
{
    global $failures, $passes;
    if ($condition) {
        $passes[] = "{$id} {$message}";
    } else {
        $failures[] = "{$id} {$message}";
    }
}

function mr_contains_all(string $source, array $needles): bool
{
    foreach ($needles as $needle) {
        if (!str_contains($source, $needle)) {
            return false;
        }
    }

    return true;
}

$service = mr_source('app/service/ManagementReviewInputService.php');
$controller = mr_source('app/controller/ManagementReview.php');
$add = mr_source('app/view/management_review/add.html');
$edit = mr_source('app/view/management_review/edit.html');
$view = mr_source('app/view/management_review/view.html');
$migration = mr_source('database/migrations/20260717_gr14_controlled_trial.sql');

mr_check(
    mr_contains_all($service, [
        '合格', '通过', 'pass',
        '不合格', '失败', 'fail',
        '限用', 'limited',
    ]),
    'MR01',
    '校准结论兼容中英文值'
);

mr_check(
    mr_contains_all($service, [
        '8.9.2',
        '内审',
        '投诉',
        'CAPA',
        '培训',
        '监督',
        '质控',
        '校准',
        '未形成/待补充',
        'detail_url',
    ]),
    'MR02',
    '管理评审输入覆盖条款分类并提供明细入口和缺数状态'
);

mr_check(
    str_contains($migration, 'input_snapshot')
    && str_contains($controller, 'ManagementReviewInputService::snapshot')
    && str_contains($controller, "'input_snapshot'"),
    'MR03',
    '创建管理评审时冻结输入快照'
);

mr_check(
    mr_contains_all($add . $view, ['inputCategories', 'category.status_label', 'category.detail_url'])
    && str_contains($add . $view, '未形成/待补充'),
    'MR04',
    '页面按分类展示形成状态并可下钻'
);

mr_check(
    preg_match('/\bname=["\']review_date["\']/', $edit) === 1
    && preg_match('/\bname=["\']title["\']/', $edit) === 1
    && preg_match('/\bname=["\']inputs["\']/', $edit) === 1
    && !str_contains($edit, 'name="name"')
    && !str_contains($edit, 'name="code"'),
    'MR05',
    '管理评审编辑页使用真实字段'
);

foreach ($passes as $pass) {
    echo "[PASS] {$pass}\n";
}
foreach ($failures as $failure) {
    fwrite(STDERR, "[FAIL] {$failure}\n");
}
exit($failures === [] ? 0 : 1);
