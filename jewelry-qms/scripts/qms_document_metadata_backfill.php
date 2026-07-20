<?php
declare(strict_types=1);

/**
 * Dry-run / apply backfill for published documents missing metadata.
 *
 * Usage:
 *   php scripts/qms_document_metadata_backfill.php
 *   QMS_METADATA_BACKFILL_APPLY=1 php scripts/qms_document_metadata_backfill.php --apply
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/common.php';

$app = new think\App();
$app->initialize();

use think\facade\Db;

$apply = in_array('--apply', $argv ?? [], true);
$applyEnv = getenv('QMS_METADATA_BACKFILL_APPLY');
$applyAllowed = $applyEnv === '1' || strtolower((string)$applyEnv) === 'true';

$rows = Db::name('documents')
    ->where('soft_delete', 0)
    ->where('status', 'published')
    ->where(function ($q) {
        $q->whereNull('effective_date')
            ->whereOr('effective_date', '')
            ->whereOr('department_id', null)
            ->whereOr('department_id', '')
            ->whereOr('review_date', null)
            ->whereOr('review_date', '');
    })
    ->field('id,doc_number,title,effective_date,department_id,review_date,status')
    ->order('doc_number', 'asc')
    ->select()
    ->toArray();

echo "mode: " . ($apply && $applyAllowed ? 'apply' : 'dry-run') . PHP_EOL;
echo "published_missing_metadata: " . count($rows) . PHP_EOL;
foreach ($rows as $row) {
    $gaps = [];
    if (trim((string)($row['effective_date'] ?? '')) === '') {
        $gaps[] = 'effective_date';
    }
    if (trim((string)($row['department_id'] ?? '')) === '') {
        $gaps[] = 'department_id(归口)';
    }
    if (trim((string)($row['review_date'] ?? '')) === '') {
        $gaps[] = 'review_date(复审)';
    }
    echo sprintf(
        "%s\t%s\tmissing=%s\n",
        $row['doc_number'],
        $row['title'],
        implode(',', $gaps)
    );
}

if ($apply && !$applyAllowed) {
    fwrite(STDERR, "refuse apply: set QMS_METADATA_BACKFILL_APPLY=1 to write\n");
    exit(2);
}

if ($apply && $applyAllowed) {
    $updated = 0;
    foreach ($rows as $row) {
        $patch = [];
        if (trim((string)($row['effective_date'] ?? '')) === '' && !empty($row['created'] ?? null)) {
            // no-op placeholder: apply only when callers supply a mapping file in future
        }
        if ($patch !== []) {
            Db::name('documents')->where('id', $row['id'])->update($patch);
            $updated++;
        }
    }
    echo "updated: {$updated}\n";
    echo "note: current apply path lists gaps only unless patch mapping is provided\n";
}

exit(0);
