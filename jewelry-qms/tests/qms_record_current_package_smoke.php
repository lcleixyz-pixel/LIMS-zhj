<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use app\service\RecordFormCurrentPackageService;

function current_package_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$workspace = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qms-current-package-' . bin2hex(random_bytes(6));
current_package_assert(mkdir($workspace, 0755, true), 'Temporary package workspace must be created');

$originalPdf = $workspace . DIRECTORY_SEPARATOR . 'original.pdf';
$correctionPdf = $workspace . DIRECTORY_SEPARATOR . 'correction.pdf';
file_put_contents($originalPdf, "%PDF-1.4\nORIGINAL\n%%EOF");
file_put_contents($correctionPdf, "%PDF-1.4\nCORRECTION\n%%EOF");

$result = RecordFormCurrentPackageService::build(
    'aa16b7d2-c15c-418e-b8e1-e98441fe880b',
    '标准物质报废申请表-001',
    $originalPdf,
    '原始记录.pdf',
    $correctionPdf,
    3,
    '2026-07-26 11:30:00',
    $workspace
);

current_package_assert(is_file($result['file_path'] ?? ''), 'Current package ZIP must be created');
current_package_assert(
    ($result['original_sha256'] ?? '') === hash_file('sha256', $originalPdf),
    'Current package must report the frozen original PDF SHA-256'
);

$zip = new \ZipArchive();
current_package_assert($zip->open((string)$result['file_path']) === true, 'Current package ZIP must be readable');
$entryNames = [];
for ($index = 0; $index < $zip->numFiles; $index++) {
    $entryNames[] = (string)$zip->getNameIndex($index);
}
current_package_assert(count($entryNames) === 3, 'Current package must contain exactly three governed artifacts');
current_package_assert(
    count(array_filter($entryNames, static fn (string $name): bool => str_starts_with($name, '00-阅读说明'))) === 1,
    'Current package must contain a reading note'
);
current_package_assert(
    count(array_filter($entryNames, static fn (string $name): bool => str_starts_with($name, '01-原始记录-'))) === 1,
    'Current package must contain the frozen original PDF'
);
current_package_assert(
    count(array_filter($entryNames, static fn (string $name): bool => str_starts_with($name, '02-更正附页-'))) === 1,
    'Current package must contain the correction appendix PDF'
);

$noteName = (string)array_values(array_filter(
    $entryNames,
    static fn (string $name): bool => str_starts_with($name, '00-阅读说明')
))[0];
$note = (string)$zip->getFromName($noteName);
$zip->close();
current_package_assert(str_contains($note, '更正记录条数：3'), 'Reading note must state correction count');
current_package_assert(str_contains($note, hash_file('sha256', $originalPdf)), 'Reading note must state original PDF hash');
current_package_assert(str_contains($note, '须与原记录一并保存'), 'Reading note must explain paper archive handling');

echo "qms_record_current_package_smoke passed\n";
