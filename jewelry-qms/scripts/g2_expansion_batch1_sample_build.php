<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

if (!function_exists('root_path')) {
    function root_path(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR;
    }
}

use app\service\G2ExpansionBatch1BlueprintService;
use app\service\RecordFormPrintService;

$outputDir = $argv[1] ?? '';
if ($outputDir === '') {
    fwrite(STDERR, "Usage: php scripts/g2_expansion_batch1_sample_build.php <output-dir>\n");
    exit(2);
}

if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Cannot create output dir: {$outputDir}\n");
    exit(2);
}

$manifest = [];
$sites = ['wulumuqi', 'hetian'];
foreach (G2ExpansionBatch1BlueprintService::templates() as $template) {
    foreach ($sites as $site) {
        $values = G2ExpansionBatch1BlueprintService::sampleValues((string)$template['doc_number'], $site);
        $html = RecordFormPrintService::render((string)$template['print_template_key'], $template, $values);
        $base = str_replace(['XZTC/BG-', '/'], ['BG-', '-'], (string)$template['doc_number']) . '-' . $site;
        $file = $outputDir . DIRECTORY_SEPARATOR . $base . '.html';
        file_put_contents($file, $html);
        $manifest[] = [
            'case' => $base,
            'usage_site' => $site,
            'doc_number' => $template['doc_number'],
            'display_doc_number' => RecordFormPrintService::displayDocNumber($template, $values),
            'name' => $template['name'],
            'print_template_key' => $template['print_template_key'],
            'field_count' => count($template['field_schema']),
            'retention' => $template['retention'],
            'acceptance_gate' => $template['acceptance_gate'] ?? '',
            'html_file' => basename($file),
            'sha256' => hash_file('sha256', $file),
        ];
    }
}

file_put_contents(
    $outputDir . DIRECTORY_SEPARATOR . 'g2-expansion-batch1-manifest.json',
    json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n"
);

echo "g2_expansion_batch1_sample_build generated " . count($manifest) . " samples\n";
