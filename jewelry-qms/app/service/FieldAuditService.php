<?php
declare(strict_types=1);

namespace app\service;

use think\Model;
use think\facade\Db;
use think\facade\Log;
use think\facade\Session;

class FieldAuditService
{
    protected static array $auditFields = [
        'Document' => ['status', 'version', 'revision', 'effective_date', 'approved_by'],
        'Capa' => ['status', 'root_cause', 'corrective_action', 'verified_by', 'verified_date'],
        'Equipment' => ['status', 'next_calibration_date', 'last_calibration_date', 'site_id'],
        'AuditFinding' => ['status', 'capa_id'],
    ];

    protected static array $sensitiveFields = ['password'];
    protected static array $jsonFields = ['field_values', 'participants'];
    protected static ?bool $tableReady = null;

    public static function shouldAuditModel(Model $model): bool
    {
        return self::auditFieldsFor($model) !== [];
    }

    public static function auditFieldsFor(Model|string $model): array
    {
        $modelName = self::modelName($model);

        return self::$auditFields[$modelName] ?? [];
    }

    public static function capture(Model $model): void
    {
        if (!self::shouldAuditModel($model)) {
            return;
        }

        try {
            if (!self::isTableReady()) {
                return;
            }

            $modelName = self::modelName($model);
            $recordId = (string)($model->getData('id') ?? '');
            if ($recordId === '') {
                return;
            }

            $origin = $model->getOrigin();
            $changed = $model->getChangedData();
            $allowedFields = array_flip(self::$auditFields[$modelName] ?? []);
            $now = date('Y-m-d H:i:s');
            $rows = [];

            foreach ($changed as $field => $newValue) {
                if (!isset($allowedFields[$field]) || in_array($field, self::$sensitiveFields, true)) {
                    continue;
                }
                $oldValue = $origin[$field] ?? null;
                if ($oldValue === $newValue) {
                    continue;
                }
                $rows[] = [
                    'id' => qms_uuid(),
                    'model_name' => $modelName,
                    'record_id' => $recordId,
                    'field_name' => $field,
                    'old_value' => self::formatAuditValue($field, $oldValue),
                    'new_value' => self::formatAuditValue($field, $newValue),
                    'changed_by' => Session::get('user.id'),
                    'changed_at' => $now,
                ];
            }

            if ($rows !== []) {
                Db::name('field_change_logs')->insertAll($rows);
            }
        } catch (\Throwable $exception) {
            Log::error('Field audit capture failed: ' . $exception->getMessage());
        }
    }

    public static function logsFor(string $modelName, string $recordId, int $limit = 50): array
    {
        try {
            if (!self::isTableReady()) {
                return [];
            }

            return Db::name('field_change_logs')
                ->where('model_name', $modelName)
                ->where('record_id', $recordId)
                ->order('changed_at', 'desc')
                ->order('id', 'desc')
                ->limit($limit)
                ->select()
                ->toArray();
        } catch (\Throwable $exception) {
            Log::error('Field audit query failed: ' . $exception->getMessage());

            return [];
        }
    }

    public static function formatAuditValue(string $field, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (in_array($field, self::$jsonFields, true) || is_array($value) || is_object($value)) {
            return '[已变更]';
        }

        $text = (string)$value;
        if (mb_strlen($text, 'UTF-8') > 500) {
            return mb_substr($text, 0, 500, 'UTF-8') . '[...截断]';
        }

        return $text;
    }

    protected static function modelName(Model|string $model): string
    {
        if (is_string($model)) {
            $parts = explode('\\', $model);

            return end($parts) ?: $model;
        }

        $class = get_class($model);
        $parts = explode('\\', $class);

        return end($parts) ?: $class;
    }

    protected static function isTableReady(): bool
    {
        if (self::$tableReady !== null) {
            return self::$tableReady;
        }

        try {
            Db::name('field_change_logs')->limit(1)->select();
            self::$tableReady = true;
        } catch (\Throwable $exception) {
            self::$tableReady = false;
        }

        return self::$tableReady;
    }
}
