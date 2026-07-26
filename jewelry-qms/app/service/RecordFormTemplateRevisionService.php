<?php
declare(strict_types=1);

namespace app\service;

use app\model\RecordFormTemplate;
use RuntimeException;
use think\facade\Db;

final class RecordFormTemplateRevisionService
{
    private const REVISION_COLUMNS = [
        'supersedes_template_id',
        'revision_root_id',
        'revision_note',
    ];

    public static function supportsVersioning(?RecordFormTemplate $template = null): bool
    {
        $template ??= new RecordFormTemplate();
        foreach (self::REVISION_COLUMNS as $column) {
            if (!$template->hasColumn($column)) {
                return false;
            }
        }

        return true;
    }

    public static function nextVersion(string $version): string
    {
        $version = trim($version);
        if ($version === '') {
            return 'A/1';
        }
        if (preg_match('/^(.*?)(\d+)$/u', $version, $matches) !== 1) {
            $next = $version . '/1';
        } else {
            $digits = (string)$matches[2];
            $incremented = (string)((int)$digits + 1);
            if (strlen($digits) > 1) {
                $incremented = str_pad($incremented, strlen($digits), '0', STR_PAD_LEFT);
            }
            $next = (string)$matches[1] . $incremented;
        }
        if (mb_strlen($next) > 20) {
            throw new RuntimeException('递增后的版本号超过 20 个字符，请先缩短当前版本号。');
        }

        return $next;
    }

    public static function createDraftRevision(RecordFormTemplate $source, string $revisionNote): array
    {
        $revisionNote = trim($revisionNote);
        if ($revisionNote === '') {
            throw new RuntimeException('请填写本次修订说明，再建立修订草稿。');
        }
        if (!self::supportsVersioning($source)) {
            throw new RuntimeException('记录模板换版结构尚未准备完成，请联系系统管理员执行版本迁移。');
        }
        if (!in_array((string)$source->status, ['trial_ready', 'published'], true)) {
            throw new RuntimeException('只有试运行就绪或已发布模板可以建立修订草稿。');
        }

        return Db::transaction(static function () use ($source, $revisionNote): array {
            $locked = RecordFormTemplate::where('id', (string)$source->id)
                ->where('soft_delete', 0)
                ->lock(true)
                ->find();
            if (!$locked) {
                throw new RuntimeException('原记录模板不存在或已被删除。');
            }

            $existing = RecordFormTemplate::where('supersedes_template_id', (string)$locked->id)
                ->where('status', 'draft')
                ->where('soft_delete', 0)
                ->order('created', 'desc')
                ->find();
            if ($existing) {
                return [
                    'template' => $existing,
                    'reused' => true,
                ];
            }

            $draft = new RecordFormTemplate();
            foreach ([
                'company_id',
                'document_id',
                'element_id',
                'procedure_doc_id',
                'doc_number',
                'canonical_doc_number',
                'trial_of_template_id',
                'name',
                'module',
                'applicable_sites',
                'responsible_position_code',
                'retention_period',
                'source_file_path',
                'source_file_name',
                'source_file_sha1',
                'print_template_key',
                'field_schema',
            ] as $field) {
                $draft->setAttr($field, $locked->getAttr($field));
            }
            $draft->id = qms_uuid();
            $draft->supersedes_template_id = (string)$locked->id;
            $draft->revision_root_id = trim((string)$locked->revision_root_id) ?: (string)$locked->id;
            $draft->revision_note = $revisionNote;
            $draft->version = self::nextVersion((string)$locked->version);
            $draft->status = 'draft';
            $draft->trial_batch = TrialModeService::trialBatch();
            $draft->trial_approved_by = null;
            $draft->trial_approved_at = null;
            $draft->trial_note = '由版本 ' . (string)$locked->version . ' 复制建立，待修订、复核和批准。';
            $draft->review_status = 'pending';
            $draft->review_note = '修订说明：' . $revisionNote;
            $draft->reviewed_at = null;
            $draft->publish = 0;
            $draft->soft_delete = 0;
            $draft->save();

            self::copyBlockLinks((string)$locked->id, (string)$draft->id);
            self::copyAssets((string)$locked->id, (string)$draft->id);

            return [
                'template' => $draft,
                'reused' => false,
            ];
        });
    }

    public static function openDraftFor(RecordFormTemplate $template): ?RecordFormTemplate
    {
        if (!self::supportsVersioning($template)) {
            return null;
        }

        return RecordFormTemplate::where('supersedes_template_id', (string)$template->id)
            ->where('status', 'draft')
            ->where('soft_delete', 0)
            ->order('created', 'desc')
            ->find();
    }

    public static function previousVersion(RecordFormTemplate $template): ?RecordFormTemplate
    {
        if (!self::supportsVersioning($template)) {
            return null;
        }
        $previousId = trim((string)$template->supersedes_template_id);

        return $previousId === ''
            ? null
            : RecordFormTemplate::where('id', $previousId)->where('soft_delete', 0)->find();
    }

    public static function history(RecordFormTemplate $template): array
    {
        if (!self::supportsVersioning($template)) {
            return [];
        }
        $rootId = trim((string)$template->revision_root_id) ?: (string)$template->id;

        return RecordFormTemplate::where('soft_delete', 0)
            ->where(static function ($query) use ($rootId) {
                $query->where('id', $rootId)->whereOr('revision_root_id', $rootId);
            })
            ->order('created', 'asc')
            ->select()
            ->toArray();
    }

    public static function conflictingNumberExists(string $docNumber, string $currentId = ''): bool
    {
        $query = RecordFormTemplate::where('soft_delete', 0)->where('doc_number', $docNumber);
        if ($currentId === '') {
            return (int)$query->count() > 0;
        }

        $current = RecordFormTemplate::where('id', $currentId)->where('soft_delete', 0)->find();
        if (!$current || !self::supportsVersioning($current)) {
            return (int)$query->where('id', '<>', $currentId)->count() > 0;
        }

        $lineageIds = array_column(self::history($current), 'id');
        if ($lineageIds === []) {
            $lineageIds = [$currentId];
        }

        return (int)$query->whereNotIn('id', $lineageIds)->count() > 0;
    }

    private static function copyBlockLinks(string $sourceId, string $draftId): void
    {
        $rows = Db::name('qms_document_block_links')
            ->where('record_form_template_id', $sourceId)
            ->where('soft_delete', 0)
            ->select()
            ->toArray();
        foreach ($rows as $row) {
            $copy = self::copyFields($row, [
                'company_id',
                'block_id',
                'element_id',
                'clause_id',
                'manual_section_id',
                'procedure_document_id',
                'position_id',
                'business_module_id',
                'relation_type',
                'confidence',
                'note',
                'publish',
                'soft_delete',
            ]);
            $copy['id'] = qms_uuid();
            $copy['record_form_template_id'] = $draftId;
            $copy['created'] = date('Y-m-d H:i:s');
            $copy['modified'] = $copy['created'];
            Db::name('qms_document_block_links')->insert($copy);
        }
    }

    private static function copyAssets(string $sourceId, string $draftId): void
    {
        $rows = Db::name('qms_document_assets')
            ->where('record_form_template_id', $sourceId)
            ->where('soft_delete', 0)
            ->select()
            ->toArray();
        foreach ($rows as $row) {
            $copy = self::copyFields($row, [
                'company_id',
                'source_kind',
                'document_id',
                'source_id',
                'original_name',
                'original_path',
                'normalized_name',
                'archived_path',
                'file_type',
                'file_sha256',
                'archive_status',
                'extracted_at',
                'extracted_text_hash',
                'markdown_path',
                'review_status',
                'source_note',
                'metadata_json',
                'publish',
                'soft_delete',
            ]);
            $copy['id'] = qms_uuid();
            $copy['record_form_template_id'] = $draftId;
            $copy['created'] = date('Y-m-d H:i:s');
            $copy['modified'] = $copy['created'];
            Db::name('qms_document_assets')->insert($copy);
        }
    }

    private static function copyFields(array $row, array $fields): array
    {
        $copy = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $row)) {
                $copy[$field] = $row[$field];
            }
        }

        return $copy;
    }
}
