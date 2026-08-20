<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

use app\service\FinalCandidateAssemblyService;

(new think\App())->initialize();

function final_candidate_output_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$sourceDir = trim((string)getenv('QMS_FINAL_CANDIDATE_SOURCE_DIR'));
final_candidate_output_assert($sourceDir !== '' && is_dir($sourceDir), '必须提供最终确认稿来源目录');
$outputDir = sys_get_temp_dir() . '/qms-final-candidate-output-' . bin2hex(random_bytes(4));

$preview = FinalCandidateAssemblyService::preview($sourceDir);
$written = FinalCandidateAssemblyService::writePackage($preview, $outputDir, 'dry-run');

foreach ([
    '01-来源清单-v0.1.json',
    '02-排除材料清单-v0.1.md',
    '03-时限裁决补丁-v0.1.json',
    '04-关键待决事项-v0.1.md',
    '05-干跑报告-v0.1.json',
    '06-候选连续正文',
] as $name) {
    final_candidate_output_assert(file_exists($outputDir . '/' . $name), '交接包缺少 ' . $name);
}
final_candidate_output_assert(count(glob($outputDir . '/06-候选连续正文/*.md') ?: []) === 65, '必须输出65份候选连续正文');
final_candidate_output_assert(($written['mode'] ?? '') === 'dry-run', '输出摘要必须记录dry-run模式');
final_candidate_output_assert(($written['file_count'] ?? 0) >= 70, '输出摘要必须覆盖清单、报告和65份正文');

echo "qms_final_candidate_output_smoke passed\n";
