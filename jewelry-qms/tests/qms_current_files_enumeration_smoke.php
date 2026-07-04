<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use app\service\CurrentFilesSeedService;

function assert_enum_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function assert_enum_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__);
$workspaceRoot = dirname($root);
$sourceRoot = $workspaceRoot . '/现用文件';

foreach ([
    'knowledge/README.md',
    'knowledge/INDEX.md',
    'knowledge/standards',
    'knowledge/internal',
    'knowledge/internal/procedures',
    'knowledge/internal/manual',
    'knowledge/cases',
] as $path) {
    assert_enum_true(file_exists($workspaceRoot . '/' . $path), 'Knowledge skeleton exists: ' . $path);
}

$manifest = CurrentFilesSeedService::enumerateProcedureFiles($sourceRoot);
assert_enum_true((int)$manifest['total_files'] === 42, 'Procedure source directory has 42 files');
assert_enum_true((int)$manifest['numbered_files'] === 38, 'Procedure source directory has 38 numbered procedure files');
assert_enum_true((int)$manifest['excluded_files'] === 4, 'Procedure source directory excludes 4 metadata files');

$docNumbers = array_column((array)$manifest['included'], 'doc_number');
foreach (['XZTC/CX-01-2022', 'XZTC/CX-01-02-2022', 'XZTC/CX-05-02-2022', 'XZTC/CX-35-2022'] as $docNumber) {
    assert_enum_true(in_array($docNumber, $docNumbers, true), 'Included procedure doc number: ' . $docNumber);
}

$excluded = [];
foreach ((array)$manifest['excluded'] as $item) {
    $excluded[(string)$item['file_name']] = (string)$item['reason'];
}
foreach (['程序文件封面.docx', '程序文件目录.docx', '程序文件批准页.docx', '程序文件修改页.docx'] as $fileName) {
    assert_enum_true(($excluded[$fileName] ?? '') === 'metadata_page', 'Metadata page excluded: ' . $fileName);
}

$paths = CurrentFilesSeedService::writeProcedureManifest($manifest);
$markdownPath = $workspaceRoot . '/' . (string)$paths['markdown'];
$jsonPath = $workspaceRoot . '/' . (string)$paths['json'];
assert_enum_true(is_file($markdownPath), 'Procedure markdown manifest is exported');
assert_enum_true(is_file($jsonPath), 'Procedure JSON manifest is exported');

$firstMarkdown = (string)file_get_contents($markdownPath);
$firstJson = (string)file_get_contents($jsonPath);
assert_enum_contains('文件总数：42', $firstMarkdown, 'Markdown manifest records total files');
assert_enum_contains('编号文件：38', $firstMarkdown, 'Markdown manifest records numbered files');
assert_enum_contains('XZTC/CX-01-2022', $firstMarkdown, 'Markdown manifest includes procedure controlled number');
assert_enum_contains('程序文件封面.docx', $firstMarkdown, 'Markdown manifest includes excluded metadata page');

CurrentFilesSeedService::writeProcedureManifest($manifest);
assert_enum_true($firstMarkdown === (string)file_get_contents($markdownPath), 'Markdown manifest export is idempotent');
assert_enum_true($firstJson === (string)file_get_contents($jsonPath), 'JSON manifest export is idempotent');

$json = json_decode($firstJson, true);
assert_enum_true(is_array($json), 'JSON manifest is valid JSON');
assert_enum_true((int)($json['numbered_files'] ?? 0) === 38, 'JSON manifest records numbered files');

echo "qms_current_files_enumeration_smoke passed\n";
