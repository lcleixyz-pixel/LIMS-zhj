<?php
declare(strict_types=1);

function trace_inheritance_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$source = (string)file_get_contents(
    dirname(__DIR__) . '/app/service/GovernedTrialResolvedDocumentService.php'
);
preg_match(
    '/private static function copyPriorTraceLinks\\(.*?^    \\}/ms',
    $source,
    $copyPriorTraceLinksMethod
);
trace_inheritance_assert($copyPriorTraceLinksMethod !== [], '应能定位旧版追溯继承方法');
trace_inheritance_assert(
    str_contains((string)$copyPriorTraceLinksMethod[0], "'confidence' => 'review_required'"),
    '继承到新治理版本的追溯关系必须降为待复核'
);
trace_inheritance_assert(
    str_contains((string)$copyPriorTraceLinksMethod[0], '待0.2逐块复核'),
    '继承关系必须保留逐块复核说明'
);

echo "qms_governed_trial_trace_inheritance_smoke passed\n";
