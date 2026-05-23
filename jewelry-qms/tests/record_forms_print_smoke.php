<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\RecordFormPrintService;

function assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

function assert_not_contains(string $needle, string $haystack, string $message): void
{
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Unexpected: ' . $needle . PHP_EOL);
        exit(1);
    }
}

function assert_throws(callable $callback, string $expectedClass, string $message, ?callable $inspect = null): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        if (!$exception instanceof $expectedClass) {
            fwrite(STDERR, $message . PHP_EOL);
            fwrite(STDERR, 'Expected exception: ' . $expectedClass . PHP_EOL);
            fwrite(STDERR, 'Actual exception: ' . get_class($exception) . PHP_EOL);
            fwrite(STDERR, 'Actual message: ' . $exception->getMessage() . PHP_EOL);
            exit(1);
        }

        if ($inspect !== null) {
            $inspect($exception);
        }

        return;
    }

    fwrite(STDERR, $message . PHP_EOL);
    fwrite(STDERR, 'Expected exception: ' . $expectedClass . PHP_EOL);
    fwrite(STDERR, 'Actual: no exception thrown' . PHP_EOL);
    exit(1);
}

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
        'bad row',
        ['name' => ['bad'], 'department' => '质量部', 'signature' => '李四'],
    ],
    'training_content' => "第一行\n第二行",
    'effect_evaluation' => '<script>alert("x")</script>',
];

$html = RecordFormPrintService::render('training_record', $template, $values);

foreach (['人员培训记录表', 'XZTC/BG-01-02', '新版记录表格填写要求', '张三'] as $needle) {
    assert_contains($needle, $html, 'Rendered HTML missing expected happy-path value');
}

assert_contains("第一行<br />\n第二行", $html, 'Preserves textarea newlines with nl2br');
assert_contains('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', $html, 'Escapes HTML values');
assert_not_contains('<script>alert("x")</script>', $html, 'Does not render raw HTML values');
assert_contains('质量部', $html, 'Keeps valid repeatable rows after malformed rows');
assert_not_contains('bad row', $html, 'Ignores non-array repeatable rows');
assert_contains('break-inside: avoid', $html, 'Print HTML asks Chromium not to split table rows across pages');
assert_contains('page-break-inside: avoid', $html, 'Print HTML includes legacy page-break protection for PDF rendering');

$printTemplates = glob(dirname(__DIR__) . '/app/record_form_print/*.php') ?: [];
foreach ($printTemplates as $printTemplate) {
    assert_contains('tablePaginationCss', file_get_contents($printTemplate) ?: '', basename($printTemplate) . ' includes shared print pagination CSS');
}

assert_throws(
    fn () => RecordFormPrintService::render('../training_record', $template, $values),
    RuntimeException::class,
    'Rejects illegal template key',
    function (Throwable $exception): void {
        assert_contains('非法打印模板标识', $exception->getMessage(), 'Illegal key error is diagnostic');
        assert_not_contains('record_form_print', $exception->getMessage(), 'Illegal key error does not expose path');
    }
);

assert_throws(
    fn () => RecordFormPrintService::render('', $template, $values),
    RuntimeException::class,
    'Rejects blank template key'
);

assert_throws(
    fn () => RecordFormPrintService::render('missing_template', $template, $values),
    RuntimeException::class,
    'Reports missing template',
    function (Throwable $exception): void {
        assert_contains('打印模板不存在', $exception->getMessage(), 'Missing template error is diagnostic');
        assert_contains('missing_template', $exception->getMessage(), 'Missing template error names requested key');
        assert_not_contains('record_form_print', $exception->getMessage(), 'Missing template error does not expose path');
    }
);

echo "record_forms_print_smoke passed\n";
