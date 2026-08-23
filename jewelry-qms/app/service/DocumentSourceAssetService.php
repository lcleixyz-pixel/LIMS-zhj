<?php
declare(strict_types=1);

namespace app\service;

use DomainException;
use RuntimeException;
use think\facade\Config;
use think\facade\Db;

final class DocumentSourceAssetService
{
    private const SNAPSHOT_RELATIVE_ROOT = '.team/交接箱/2026-08-20-8021候选试装/源文件快照';
    private const ALLOWED_EXTENSIONS = ['doc', 'docx', 'pdf', 'wps'];

    public static function previewFinalCandidateSnapshot(string $sourceDir): array
    {
        $sourceDir = rtrim(trim($sourceDir), DIRECTORY_SEPARATOR);
        $sourceRoot = realpath($sourceDir);
        $allowedRoot = self::allowedSnapshotRoot();
        $errors = [];
        if ($sourceRoot === false || !is_dir($sourceRoot)) {
            $errors[] = '来源Word快照目录不存在';
        } elseif ($allowedRoot === false || !hash_equals($allowedRoot, $sourceRoot)) {
            $errors[] = '来源Word只能从固定只读快照目录补链';
        }

        $files = $errors === [] ? (glob($sourceRoot . DIRECTORY_SEPARATOR . '*.docx') ?: []) : [];
        sort($files, SORT_NATURAL);
        if (count($files) !== 65) {
            $errors[] = '来源Word数量应为65，当前为' . count($files);
        }

        $documents = Db::name('documents')
            ->where('version', FinalCandidateManifestService::VERSION)
            ->whereLike('doc_number', FinalCandidateManifestService::TRIAL_PREFIX . '%')
            ->where('soft_delete', 0)
            ->select()
            ->toArray();
        $bySourceName = [];
        foreach ($documents as $document) {
            $reason = self::changeMetadata((string)($document['change_reason'] ?? ''));
            if ($reason === []) {
                continue;
            }
            $sourceName = basename(str_replace('\\', '/', (string)($reason['source_snapshot'] ?? '')));
            if ($sourceName === '') {
                continue;
            }
            $bySourceName[$sourceName] = [
                'document' => $document,
                'source_sha256' => (string)($reason['source_sha256'] ?? ''),
                'resolved_text_sha256' => (string)($reason['resolved_text_sha256'] ?? ''),
            ];
        }

        $structures = Db::name('qms_structured_documents')
            ->where('version', FinalCandidateManifestService::VERSION)
            ->whereLike('doc_number', FinalCandidateManifestService::TRIAL_PREFIX . '%')
            ->where('soft_delete', 0)
            ->select()
            ->toArray();
        $structureByDocument = [];
        foreach ($structures as $structure) {
            $structureByDocument[(string)$structure['document_id']] = $structure;
        }

        $items = [];
        $createCount = 0;
        $updateCount = 0;
        foreach ($files as $path) {
            $name = basename($path);
            $match = $bySourceName[$name] ?? null;
            if (!is_array($match)) {
                $errors[] = $name . ' 未匹配到0.3候选文件';
                continue;
            }
            $document = (array)$match['document'];
            $expectedHash = (string)$match['source_sha256'];
            $actualHash = hash_file('sha256', $path);
            if (!is_string($actualHash) || !preg_match('/^[a-f0-9]{64}$/', $expectedHash)
                || !hash_equals($expectedHash, $actualHash)
            ) {
                $errors[] = $name . ' 与候选登记SHA-256不一致';
                continue;
            }
            $documentId = (string)$document['id'];
            $structure = $structureByDocument[$documentId] ?? null;
            if (!is_array($structure)) {
                $errors[] = $name . ' 未匹配到结构化文件';
                continue;
            }
            $existing = Db::name('qms_document_assets')
                ->where('document_id', $documentId)
                ->where('soft_delete', 0)
                ->find();
            if (is_array($existing)) {
                $updateCount++;
                $existingHash = trim((string)($existing['file_sha256'] ?? ''));
                if ($existingHash !== '' && !hash_equals($existingHash, $expectedHash)) {
                    $errors[] = $name . ' 已有关联资产发生哈希漂移';
                }
            } else {
                $createCount++;
            }
            $items[] = [
                'document_id' => $documentId,
                'structured_document_id' => (string)$structure['id'],
                'document_role' => (string)$structure['document_role'],
                'document_status' => (string)$document['status'],
                'source_name' => $name,
                'stored_path' => self::storedPathFor($path),
                'source_sha256' => $expectedHash,
                'resolved_text_sha256' => (string)$match['resolved_text_sha256'],
                'markdown_path' => (string)$structure['markdown_path'],
                'existing_asset_id' => is_array($existing) ? (string)$existing['id'] : '',
            ];
        }

        if (count($documents) !== 65) {
            $errors[] = '0.3候选文件数量应为65，当前为' . count($documents);
        }
        if (count($structures) !== 65) {
            $errors[] = '0.3结构化文件数量应为65，当前为' . count($structures);
        }
        if (count($items) !== 65) {
            $errors[] = '成功匹配的来源资产数量应为65，当前为' . count($items);
        }

        return [
            'mode' => 'inspect_only',
            'validation' => [
                'ok' => $errors === [],
                'errors' => array_values(array_unique($errors)),
            ],
            'counts' => [
                'source_files' => count($files),
                'candidate_documents' => count($documents),
                'structured_documents' => count($structures),
                'matched_documents' => count($items),
                'assets_to_create' => $createCount,
                'assets_to_update' => $updateCount,
            ],
            'items' => $items,
        ];
    }

    public static function applyFinalCandidateSnapshot(string $sourceDir): array
    {
        self::assertWritableTrialEnvironment();
        $preview = self::previewFinalCandidateSnapshot($sourceDir);
        if (($preview['validation']['ok'] ?? false) !== true) {
            throw new RuntimeException('来源Word补链预览未通过：' . implode('；', $preview['validation']['errors'] ?? []));
        }

        Db::transaction(function () use ($preview): void {
            foreach ((array)$preview['items'] as $item) {
                self::upsertItem($item);
            }
            $verification = self::verifyFinalCandidates();
            if (($verification['ok'] ?? false) !== true) {
                throw new RuntimeException('来源Word补链事务验证失败：' . implode('；', $verification['errors'] ?? []));
            }
        });

        return [
            'mode' => 'trial_apply',
            'source_validation' => $preview['validation'],
            'planned' => $preview['counts'],
            'validation' => self::verifyFinalCandidates(),
        ];
    }

    public static function upsertCandidateAsset(
        array $document,
        string $documentId,
        string $structuredDocumentId,
        string $markdownPath
    ): string {
        $sourceName = basename((string)($document['file_name'] ?? ''));
        $expectedHash = trim((string)($document['source_sha256'] ?? ''));
        $snapshotPath = self::snapshotPathForName($sourceName);
        if ($snapshotPath === null || self::resolveSourcePath(self::storedPathFor($snapshotPath), $expectedHash) === null) {
            throw new RuntimeException($sourceName . ' 的固定来源快照不存在或哈希不一致');
        }
        return self::upsertItem([
            'document_id' => $documentId,
            'structured_document_id' => $structuredDocumentId,
            'document_role' => (string)($document['document_role'] ?? ''),
            'document_status' => (string)($document['status'] ?? 'draft'),
            'source_name' => $sourceName,
            'stored_path' => self::storedPathFor($snapshotPath),
            'source_sha256' => $expectedHash,
            'resolved_text_sha256' => (string)($document['resolved_text_sha256'] ?? ''),
            'markdown_path' => $markdownPath,
        ]);
    }

    public static function assetForDocument(string $documentId): ?array
    {
        $asset = Db::name('qms_document_assets')
            ->where('document_id', trim($documentId))
            ->where('archive_status', 'archived')
            ->where('soft_delete', 0)
            ->order('modified', 'desc')
            ->find();
        if (!is_array($asset)) {
            return null;
        }
        $resolved = self::resolveSourcePath(
            (string)($asset['archived_path'] ?: $asset['original_path']),
            (string)$asset['file_sha256']
        );
        $asset['resolved_path'] = $resolved;
        $asset['source_available'] = $resolved !== null;

        return $asset;
    }

    public static function resolveSourcePath(string $storedPath, string $expectedHash): ?string
    {
        $storedPath = str_replace('\\', '/', trim($storedPath));
        $expectedHash = strtolower(trim($expectedHash));
        if ($storedPath === '' || str_starts_with($storedPath, '/') || str_contains($storedPath, '../')
            || !str_starts_with($storedPath, self::SNAPSHOT_RELATIVE_ROOT . '/')
            || preg_match('/^[a-f0-9]{64}$/', $expectedHash) !== 1
        ) {
            return null;
        }
        $extension = strtolower((string)pathinfo($storedPath, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return null;
        }

        $root = self::workspaceRoot();
        $allowedRoot = self::allowedSnapshotRoot();
        $candidate = realpath(rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $storedPath);
        if ($allowedRoot === false || $candidate === false || !is_file($candidate)
            || !str_starts_with($candidate, $allowedRoot . DIRECTORY_SEPARATOR)
        ) {
            return null;
        }
        $actualHash = hash_file('sha256', $candidate);
        if (!is_string($actualHash) || !hash_equals($expectedHash, $actualHash)) {
            return null;
        }

        return $candidate;
    }

    public static function changeMetadata(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($raw);
        for ($index = 0; $index < $length; $index++) {
            $character = $raw[$index];
            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($character === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($character === '"') {
                    $inString = false;
                }
                continue;
            }
            if ($character === '"') {
                $inString = true;
                continue;
            }
            if ($character === '{') {
                $depth++;
                continue;
            }
            if ($character === '}') {
                $depth--;
                if ($depth === 0) {
                    $candidate = json_decode(substr($raw, 0, $index + 1), true);
                    return is_array($candidate) ? $candidate : [];
                }
            }
        }

        return [];
    }

    public static function verifyFinalCandidates(): array
    {
        $errors = [];
        $rows = Db::name('qms_structured_documents')->alias('structure')
            ->join('qms_document_assets asset', 'asset.id=structure.source_asset_id')
            ->where('structure.version', FinalCandidateManifestService::VERSION)
            ->whereLike('structure.doc_number', FinalCandidateManifestService::TRIAL_PREFIX . '%')
            ->where('structure.soft_delete', 0)
            ->where('asset.soft_delete', 0)
            ->field('structure.id,structure.document_id,structure.source_asset_id,asset.archived_path,asset.file_sha256')
            ->select()
            ->toArray();
        if (count($rows) !== 65) {
            $errors[] = '来源资产和结构化文件的有效关联应为65，当前为' . count($rows);
        }
        foreach ($rows as $row) {
            if (self::resolveSourcePath((string)$row['archived_path'], (string)$row['file_sha256']) === null) {
                $errors[] = (string)$row['document_id'] . ' 来源Word不可用或哈希不一致';
            }
        }

        return [
            'ok' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'counts' => [
                'linked_source_assets' => count($rows),
                'valid_source_files' => count($rows) - count($errors),
            ],
        ];
    }

    private static function upsertItem(array $item): string
    {
        $documentId = trim((string)($item['document_id'] ?? ''));
        $structuredDocumentId = trim((string)($item['structured_document_id'] ?? ''));
        $sourceName = basename((string)($item['source_name'] ?? ''));
        $storedPath = (string)($item['stored_path'] ?? '');
        $sourceHash = strtolower(trim((string)($item['source_sha256'] ?? '')));
        if ($documentId === '' || $structuredDocumentId === '' || $sourceName === ''
            || self::resolveSourcePath($storedPath, $sourceHash) === null
        ) {
            throw new RuntimeException('来源资产写入参数不完整或来源文件校验失败');
        }

        $existing = Db::name('qms_document_assets')
            ->where('document_id', $documentId)
            ->where('soft_delete', 0)
            ->find();
        if (is_array($existing)) {
            $existingHash = trim((string)($existing['file_sha256'] ?? ''));
            if ($existingHash !== '' && !hash_equals($existingHash, $sourceHash)) {
                throw new RuntimeException($sourceName . ' 已有关联来源发生哈希漂移，拒绝覆盖');
            }
        }

        $assetId = (string)($existing['id'] ?? qms_uuid());
        $role = (string)($item['document_role'] ?? '');
        $now = date('Y-m-d H:i:s');
        $row = [
            'company_id' => (string)Config::get('qms.company_id'),
            'source_kind' => in_array($role, ['quality_manual', 'procedure', 'work_instruction'], true) ? $role : 'reference_file',
            'document_id' => $documentId,
            'source_id' => null,
            'record_form_template_id' => null,
            'original_name' => $sourceName,
            'original_path' => $storedPath,
            'normalized_name' => $sourceName,
            'archived_path' => $storedPath,
            'file_type' => strtolower((string)pathinfo($sourceName, PATHINFO_EXTENSION)),
            'file_sha256' => $sourceHash,
            'archive_status' => 'archived',
            'extracted_at' => $now,
            'extracted_text_hash' => (string)($item['resolved_text_sha256'] ?? ''),
            'markdown_path' => (string)($item['markdown_path'] ?? ''),
            'review_status' => (string)($item['document_status'] ?? '') === 'obsolete' ? 'obsolete' : 'structured',
            'source_note' => '8021治理阶段来源Word快照；只读追溯，不替代纸质受控文件。',
            'publish' => 1,
            'soft_delete' => 0,
            'modified' => $now,
        ];
        if (is_array($existing)) {
            Db::name('qms_document_assets')->where('id', $assetId)->update($row);
        } else {
            $row['id'] = $assetId;
            $row['created'] = $now;
            Db::name('qms_document_assets')->insert($row);
        }
        Db::name('qms_structured_documents')
            ->where('id', $structuredDocumentId)
            ->where('document_id', $documentId)
            ->where('soft_delete', 0)
            ->update(['source_asset_id' => $assetId, 'modified' => $now]);

        return $assetId;
    }

    private static function assertWritableTrialEnvironment(): void
    {
        $errors = FinalCandidateAssemblyService::writableEnvironmentErrors();
        if ($errors !== []) {
            throw new DomainException('8021来源资产补链拒绝写入：' . implode('；', $errors));
        }
        if (DocumentOperationModeService::current() !== DocumentOperationModeService::PAPER_GOVERNANCE) {
            throw new DomainException('来源资产补链仅允许在纸质运行治理阶段执行');
        }
    }

    private static function snapshotPathForName(string $sourceName): ?string
    {
        if ($sourceName === '' || basename($sourceName) !== $sourceName) {
            return null;
        }
        $root = self::allowedSnapshotRoot();
        if ($root === false) {
            return null;
        }
        $candidate = realpath($root . DIRECTORY_SEPARATOR . $sourceName);
        return $candidate !== false && is_file($candidate) && str_starts_with($candidate, $root . DIRECTORY_SEPARATOR)
            ? $candidate
            : null;
    }

    private static function storedPathFor(string $absolutePath): string
    {
        $absolutePath = str_replace('\\', '/', trim($absolutePath));
        $marker = '/.team/';
        $position = strpos($absolutePath, $marker);
        if ($position !== false) {
            return '.team/' . ltrim(substr($absolutePath, $position + strlen($marker)), '/');
        }
        if (str_starts_with($absolutePath, '/.team/')) {
            return '.team/' . ltrim(substr($absolutePath, strlen('/.team/')), '/');
        }
        throw new RuntimeException('来源文件必须位于.team固定快照目录');
    }

    private static function workspaceRoot(): string
    {
        return is_dir('/.team') ? '/' : dirname(__DIR__, 3);
    }

    private static function allowedSnapshotRoot(): string|false
    {
        return realpath(rtrim(self::workspaceRoot(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . self::SNAPSHOT_RELATIVE_ROOT);
    }
}
