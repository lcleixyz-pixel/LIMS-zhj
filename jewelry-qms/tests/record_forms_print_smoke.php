<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\RecordFormPrintService;

$template = [
    'doc_number' => 'XZTC/BG-01-02',
    'name' => '人员培训记录表',
    'version' => 'A/0',
];
$values = [
    'training_date' => '2026-05-22',
    'training_topic' => '新版记录表格填写要求',
    'trainer' => '质量负责人',
    'attendees' => [
        ['name' => '张三', 'department' => '检测室', 'signature' => '张三'],
    ],
    'effect_evaluation' => '现场问答合格',
];

$html = RecordFormPrintService::render('training_record', $template, $values);

foreach (['人员培训记录表', 'XZTC/BG-01-02', '新版记录表格填写要求', '张三'] as $needle) {
    if (!str_contains($html, $needle)) {
        fwrite(STDERR, 'Rendered HTML missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

echo "record_forms_print_smoke passed\n";
